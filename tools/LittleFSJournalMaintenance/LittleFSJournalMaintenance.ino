#include <LittleFS.h>
#include <math.h>
#include <stddef.h>
#include <stdint.h>
#include <string.h>

namespace {

const char* const EVENTS_PATH = "/events.bin";
const char* const PENDING_PATHS[] = {
  "/pending0.bin",
  "/pending1.bin",
};
const char* const CONFIRMATION = "INICIALIZAR JOURNAL";
const uint32_t QUEUE_MAGIC = 0x49544556UL;
const uint8_t QUEUE_VERSION = 1;
const uint8_t QUEUE_CAPACITY = 8;
const uint32_t PENDING_MAGIC = 0x50454E44UL;
const uint8_t PENDING_VERSION = 1;

enum EventType : uint8_t {
  EVENT_DOOR_OPEN = 1,
  EVENT_DOOR_CLOSED_LOCKED = 2,
  EVENT_DOOR_CLOSED_UNLOCKED = 3,
  EVENT_VIBRATION = 4,
};

enum DoorState : int8_t {
  DOOR_UNKNOWN = 0,
  DOOR_OPEN = 1,
  DOOR_CLOSED = 2,
};

enum LockState : int8_t {
  LOCK_UNKNOWN = 0,
  LOCK_LOCKED = 1,
  LOCK_UNLOCKED = 2,
};

struct PendingEvent {
  char uuid[37];
  char detectedAt[26];
  uint8_t type;
  int8_t doorState;
  int8_t lockState;
  uint8_t hasDistance;
  float distanceCm;
};

struct EventQueue {
  uint32_t magic;
  uint8_t version;
  uint8_t count;
  PendingEvent events[QUEUE_CAPACITY];
};

struct PendingTransitionRecord {
  uint32_t magic;
  uint8_t version;
  uint8_t active;
  uint16_t reserved;
  uint32_t sequence;
  PendingEvent event;
  uint32_t checksum;
};

static_assert(sizeof(PendingEvent) == 72, "Unexpected ESP8266 PendingEvent layout");
static_assert(offsetof(PendingTransitionRecord, checksum) == 84,
              "Unexpected ESP8266 journal checksum offset");
static_assert(sizeof(PendingTransitionRecord) == 88,
              "Unexpected ESP8266 journal record size");
static_assert(sizeof(EventQueue) == 584, "Unexpected ESP8266 queue layout");

enum FileState : uint8_t {
  FILE_ABSENT,
  FILE_VALID,
  FILE_DAMAGED,
  FILE_UNVERIFIABLE,
};

struct QueueInspection {
  FileState state;
  int error;
  size_t size;
  EventQueue queue;
};

struct SlotInspection {
  FileState state;
  int error;
  size_t size;
  PendingTransitionRecord record;
};

struct JournalInspection {
  QueueInspection queue;
  SlotInspection slots[2];
};

bool isCanonicalUtcTimestamp(const char* timestamp, size_t length) {
  static const uint8_t digitPositions[] = {
    0, 1, 2, 3, 5, 6, 8, 9, 11, 12, 14, 15, 17, 18
  };
  if (length != 26 || timestamp[19] != '+' || timestamp[20] != '0'
      || timestamp[21] != '0' || timestamp[22] != ':'
      || timestamp[23] != '0' || timestamp[24] != '0'
      || timestamp[25] != '\0' || timestamp[4] != '-'
      || timestamp[7] != '-' || timestamp[10] != 'T'
      || timestamp[13] != ':' || timestamp[16] != ':') {
    return false;
  }

  for (uint8_t i = 0; i < sizeof(digitPositions); i++) {
    const char ch = timestamp[digitPositions[i]];
    if (ch < '0' || ch > '9') {
      return false;
    }
  }

  const int year = (timestamp[0] - '0') * 1000
      + (timestamp[1] - '0') * 100
      + (timestamp[2] - '0') * 10
      + (timestamp[3] - '0');
  const int month = (timestamp[5] - '0') * 10 + (timestamp[6] - '0');
  const int day = (timestamp[8] - '0') * 10 + (timestamp[9] - '0');
  const int hour = (timestamp[11] - '0') * 10 + (timestamp[12] - '0');
  const int minute = (timestamp[14] - '0') * 10 + (timestamp[15] - '0');
  const int second = (timestamp[17] - '0') * 10 + (timestamp[18] - '0');
  if (year < 1970 || month < 1 || month > 12 || hour > 23
      || minute > 59 || second > 59) {
    return false;
  }

  static const uint8_t daysByMonth[] = {
    31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31
  };
  uint8_t days = daysByMonth[month - 1];
  const bool leapYear = (year % 4 == 0)
      && ((year % 100 != 0) || (year % 400 == 0));
  if (month == 2 && leapYear) {
    days++;
  }
  return day >= 1 && day <= days;
}

bool isCanonicalUuid(const char* uuid, size_t length) {
  if (length != 37 || uuid[36] != '\0') {
    return false;
  }
  for (uint8_t i = 0; i < 36; i++) {
    if (i == 8 || i == 13 || i == 18 || i == 23) {
      if (uuid[i] != '-') {
        return false;
      }
    } else if (!((uuid[i] >= '0' && uuid[i] <= '9')
                 || (uuid[i] >= 'a' && uuid[i] <= 'f')
                 || (uuid[i] >= 'A' && uuid[i] <= 'F'))) {
      return false;
    }
  }
  return true;
}

bool isStructurallyValidEvent(const PendingEvent& event) {
  if (!isCanonicalUuid(event.uuid, sizeof(event.uuid))
      || !isCanonicalUtcTimestamp(
          event.detectedAt,
          sizeof(event.detectedAt))
      || event.hasDistance > 1) {
    return false;
  }

  if (event.type == EVENT_DOOR_OPEN) {
    return event.doorState == DOOR_OPEN
        && event.lockState == LOCK_UNKNOWN
        && (!event.hasDistance
            || (isfinite(event.distanceCm) && event.distanceCm >= 0.0f));
  }
  if (event.type == EVENT_DOOR_CLOSED_LOCKED) {
    return event.doorState == DOOR_CLOSED
        && event.lockState == LOCK_LOCKED
        && (!event.hasDistance
            || (isfinite(event.distanceCm) && event.distanceCm >= 0.0f));
  }
  if (event.type == EVENT_DOOR_CLOSED_UNLOCKED) {
    return event.doorState == DOOR_CLOSED
        && event.lockState == LOCK_UNLOCKED
        && (!event.hasDistance
            || (isfinite(event.distanceCm) && event.distanceCm >= 0.0f));
  }
  return event.type == EVENT_VIBRATION
      && event.doorState == DOOR_UNKNOWN
      && event.lockState == LOCK_UNKNOWN
      && event.hasDistance == 0;
}

uint32_t checksumPendingRecord(const PendingTransitionRecord& record) {
  const uint8_t* bytes = reinterpret_cast<const uint8_t*>(&record);
  uint32_t checksum = 2166136261UL;
  for (size_t i = 0; i < offsetof(PendingTransitionRecord, checksum); i++) {
    checksum = (checksum ^ bytes[i]) * 16777619UL;
  }
  return checksum;
}

bool validPendingRecord(const PendingTransitionRecord& record) {
  if (record.magic != PENDING_MAGIC
      || record.version != PENDING_VERSION
      || record.active > 1
      || record.checksum != checksumPendingRecord(record)) {
    return false;
  }
  return record.active == 0 || isStructurallyValidEvent(record.event);
}

bool samePendingEvent(const PendingEvent& left, const PendingEvent& right) {
  return strcmp(left.uuid, right.uuid) == 0
      && strcmp(left.detectedAt, right.detectedAt) == 0
      && left.type == right.type
      && left.doorState == right.doorState
      && left.lockState == right.lockState
      && left.hasDistance == right.hasDistance
      && left.distanceCm == right.distanceCm;
}

bool validQueue(const EventQueue& queue) {
  if (queue.magic != QUEUE_MAGIC
      || queue.version != QUEUE_VERSION
      || queue.count > QUEUE_CAPACITY) {
    return false;
  }
  for (uint8_t i = 0; i < queue.count; i++) {
    if (!isStructurallyValidEvent(queue.events[i])) {
      return false;
    }
  }
  return true;
}

QueueInspection inspectQueue() {
  QueueInspection result = {};
  size_t size = 0;
  uint8_t type = 0;
  const int statResult = LittleFS.statPath(EVENTS_PATH, &size, &type);
  result.error = statResult;
  result.size = size;
  if (statResult == LFS_ERR_NOENT) {
    result.state = FILE_ABSENT;
    return result;
  }
  if (statResult != 0) {
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  if (type != LFS_TYPE_REG || size != sizeof(result.queue)) {
    result.state = FILE_DAMAGED;
    return result;
  }

  File file = LittleFS.open(EVENTS_PATH, "r");
  if (!file) {
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  if (file.size() != sizeof(result.queue)) {
    file.close();
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  const int readCount = file.read(
      reinterpret_cast<uint8_t*>(&result.queue),
      sizeof(result.queue));
  file.close();
  if (readCount != static_cast<int>(sizeof(result.queue))) {
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  result.state = validQueue(result.queue) ? FILE_VALID : FILE_DAMAGED;
  return result;
}

SlotInspection inspectSlot(uint8_t index) {
  SlotInspection result = {};
  size_t size = 0;
  uint8_t type = 0;
  const int statResult = LittleFS.statPath(
      PENDING_PATHS[index],
      &size,
      &type);
  result.error = statResult;
  result.size = size;
  if (statResult == LFS_ERR_NOENT) {
    result.state = FILE_ABSENT;
    return result;
  }
  if (statResult != 0) {
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  if (type != LFS_TYPE_REG || size != sizeof(result.record)) {
    result.state = FILE_DAMAGED;
    return result;
  }

  File file = LittleFS.open(PENDING_PATHS[index], "r");
  if (!file) {
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  if (file.size() != sizeof(result.record)) {
    file.close();
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  const int readCount = file.read(
      reinterpret_cast<uint8_t*>(&result.record),
      sizeof(result.record));
  file.close();
  if (readCount != static_cast<int>(sizeof(result.record))) {
    result.state = FILE_UNVERIFIABLE;
    return result;
  }
  result.state = validPendingRecord(result.record)
      ? FILE_VALID
      : FILE_DAMAGED;
  return result;
}

JournalInspection inspectAll() {
  JournalInspection result = {};
  result.queue = inspectQueue();
  result.slots[0] = inspectSlot(0);
  result.slots[1] = inspectSlot(1);
  return result;
}

const char* stateLabel(FileState state) {
  switch (state) {
    case FILE_ABSENT:
      return "AUSENTE VERIFICABLE";
    case FILE_VALID:
      return "EXISTENTE VALIDA";
    case FILE_DAMAGED:
      return "DANADA";
    case FILE_UNVERIFIABLE:
      return "NO VERIFICABLE";
  }
  return "NO VERIFICABLE";
}

void printQueue(const QueueInspection& queue) {
  Serial.printf("%s: %s", EVENTS_PATH, stateLabel(queue.state));
  if (queue.state == FILE_VALID) {
    Serial.printf(
        " (size=%u, version=%u, eventos=%u; formato sin checksum)",
        static_cast<unsigned int>(queue.size),
        queue.queue.version,
        queue.queue.count);
  } else if (queue.state == FILE_UNVERIFIABLE) {
    if (queue.error == 0) {
      Serial.print(" (stat OK; open/size/read no verificable)");
    } else {
      Serial.printf(" (stat rc=%d)", queue.error);
    }
  } else if (queue.state == FILE_DAMAGED) {
    Serial.printf(" (size=%u o estructura/version invalidas)",
                  static_cast<unsigned int>(queue.size));
  }
  Serial.println();
}

void printSlot(uint8_t index, const SlotInspection& slot) {
  Serial.printf("%s: %s", PENDING_PATHS[index], stateLabel(slot.state));
  if (slot.state == FILE_VALID) {
    Serial.printf(
        " (size=%u, version=%u, active=%u, sequence=%lu, checksum=OK)",
        static_cast<unsigned int>(slot.size),
        slot.record.version,
        slot.record.active,
        static_cast<unsigned long>(slot.record.sequence));
  } else if (slot.state == FILE_UNVERIFIABLE) {
    if (slot.error == 0) {
      Serial.print(" (stat OK; open/size/read no verificable)");
    } else {
      Serial.printf(" (stat rc=%d)", slot.error);
    }
  } else if (slot.state == FILE_DAMAGED) {
    Serial.printf(" (size=%u o estructura/version/checksum invalidos)",
                  static_cast<unsigned int>(slot.size));
  }
  Serial.println();
}

void printInspection(const JournalInspection& inspection) {
  printQueue(inspection.queue);
  printSlot(0, inspection.slots[0]);
  printSlot(1, inspection.slots[1]);
}

bool journalsCompatible(const SlotInspection& first,
                        const SlotInspection& second) {
  if (first.state != FILE_VALID || second.state != FILE_VALID) {
    return true;
  }
  const uint32_t delta =
      second.record.sequence - first.record.sequence;
  if (delta == 0x80000000UL) {
    return false;
  }
  if (delta == 0
      && (first.record.active != second.record.active
          || (first.record.active
              && !samePendingEvent(first.record.event, second.record.event)))) {
    return false;
  }
  return true;
}

bool sameSnapshot(const JournalInspection& before,
                  const JournalInspection& after) {
  if (before.queue.state != after.queue.state
      || before.slots[0].state != after.slots[0].state
      || before.slots[1].state != after.slots[1].state) {
    return false;
  }
  if (before.queue.state == FILE_VALID
      && memcmp(&before.queue.queue,
                &after.queue.queue,
                sizeof(before.queue.queue)) != 0) {
    return false;
  }
  for (uint8_t i = 0; i < 2; i++) {
    if (before.slots[i].state == FILE_VALID
        && memcmp(&before.slots[i].record,
                  &after.slots[i].record,
                  sizeof(before.slots[i].record)) != 0) {
      return false;
    }
  }
  return true;
}

bool queueUnchanged(const QueueInspection& before,
                    const QueueInspection& after) {
  if (before.state != after.state) {
    return false;
  }
  if (before.state == FILE_VALID) {
    return memcmp(&before.queue, &after.queue, sizeof(before.queue)) == 0;
  }
  return before.state == FILE_ABSENT;
}

bool safeToInitialize(const JournalInspection& inspection) {
  if (inspection.queue.state != FILE_ABSENT
      && inspection.queue.state != FILE_VALID) {
    Serial.println("ABORTADO: /events.bin no es verificable; no se escribira.");
    return false;
  }
  for (uint8_t i = 0; i < 2; i++) {
    if (inspection.slots[i].state != FILE_ABSENT
        && inspection.slots[i].state != FILE_VALID) {
      Serial.println("ABORTADO: hay una ranura danada o no verificable; no se escribira ninguna ranura.");
      return false;
    }
  }
  if (!journalsCompatible(inspection.slots[0], inspection.slots[1])) {
    Serial.println("ABORTADO: las secuencias existentes son ambiguas; no se escribira.");
    return false;
  }
  return true;
}

bool readConfirmation() {
  char input[sizeof("INICIALIZAR JOURNAL")] = {};
  size_t length = 0;
  bool overflow = false;
  const unsigned long started = millis();
  Serial.printf(
      "Para crear solo las ranuras ausentes, escriba exactamente: %s\n",
      CONFIRMATION);

  while (millis() - started < 60000UL) {
    while (Serial.available()) {
      const int value = Serial.read();
      if (value == '\r') {
        continue;
      }
      if (value == '\n') {
        input[length] = '\0';
        return !overflow && strcmp(input, CONFIRMATION) == 0;
      }
      if (length < sizeof(input) - 1) {
        input[length++] = static_cast<char>(value);
      } else {
        overflow = true;
      }
    }
    delay(10);
  }
  Serial.println("ABORTADO: tiempo de confirmacion agotado.");
  return false;
}

void makeInactiveRecord(uint32_t sequence,
                        PendingTransitionRecord& record) {
  memset(&record, 0, sizeof(record));
  record.magic = PENDING_MAGIC;
  record.version = PENDING_VERSION;
  record.active = 0;
  record.sequence = sequence;
  record.checksum = checksumPendingRecord(record);
}

bool createRecordExclusive(uint8_t index,
                           const PendingTransitionRecord& record) {
  size_t currentSize = 0;
  uint8_t currentType = 0;
  const int statResult = LittleFS.statPath(
      PENDING_PATHS[index],
      &currentSize,
      &currentType);
  if (statResult != LFS_ERR_NOENT) {
    Serial.printf(
        "ABORTADO: %s ya no se demuestra ausente (stat rc=%d); sin escritura.\n",
        PENDING_PATHS[index],
        statResult);
    return false;
  }

  size_t bytesWritten = 0;
  const int writeError = LittleFS.writeExclusive(
      PENDING_PATHS[index],
      reinterpret_cast<const uint8_t*>(&record),
      sizeof(record),
      &bytesWritten);
  if (writeError != 0 || bytesWritten != sizeof(record)) {
    Serial.printf(
        "ABORTADO: escritura exclusiva de %s fallo (lfs rc=%d, %u/%u bytes); no se reintenta.\n",
        PENDING_PATHS[index],
        writeError,
        static_cast<unsigned int>(bytesWritten),
        static_cast<unsigned int>(sizeof(record)));
    return false;
  }

  const SlotInspection verified = inspectSlot(index);
  if (verified.state != FILE_VALID
      || verified.record.active != 0
      || verified.record.sequence != record.sequence
      || verified.record.checksum != record.checksum) {
    Serial.printf(
        "ABORTADO: la verificacion de %s fallo; no se intentara reparar.\n",
        PENDING_PATHS[index]);
    return false;
  }

  Serial.printf(
      "CREADA Y VERIFICADA: %s (sequence=%lu, inactive, checksum=OK).\n",
      PENDING_PATHS[index],
      static_cast<unsigned long>(record.sequence));
  return true;
}

bool initializeMissingSlots(const JournalInspection& inspection) {
  const bool absent0 = inspection.slots[0].state == FILE_ABSENT;
  const bool absent1 = inspection.slots[1].state == FILE_ABSENT;
  if (!absent0 && !absent1) {
    Serial.println("No hay ranuras ausentes; no se escribio ningun archivo.");
    return true;
  }

  if (absent0 && absent1) {
    PendingTransitionRecord first = {};
    PendingTransitionRecord second = {};
    makeInactiveRecord(0, first);
    makeInactiveRecord(1, second);
    return createRecordExclusive(0, first)
        && createRecordExclusive(1, second);
  }

  const uint8_t missingIndex = absent0 ? 0 : 1;
  const uint8_t existingIndex = absent0 ? 1 : 0;
  PendingTransitionRecord missing = {};
  makeInactiveRecord(
      inspection.slots[existingIndex].record.sequence - 1,
      missing);
  return createRecordExclusive(missingIndex, missing);
}

void runMaintenance() {
  if (!LittleFS.setConfig(LittleFSConfig(false))) {
    Serial.println("ABORTADO: no se pudo establecer la configuracion sin autoformato.");
    return;
  }
  if (!LittleFS.begin()) {
    Serial.println("ABORTADO: no se pudo montar LittleFS; autoformato deshabilitado.");
    return;
  }

  const JournalInspection before = inspectAll();
  Serial.println("Inspeccion de solo lectura:");
  printInspection(before);
  if (!safeToInitialize(before)) {
    return;
  }

  const bool hasAbsentSlot =
      before.slots[0].state == FILE_ABSENT
      || before.slots[1].state == FILE_ABSENT;
  if (!hasAbsentSlot) {
    Serial.println("Ambas ranuras ya existen y son validas; no hace falta inicializar.");
  } else {
    if (!readConfirmation()) {
      Serial.println("ABORTADO: no se recibio la confirmacion exacta.");
      return;
    }

    const JournalInspection current = inspectAll();
    if (!sameSnapshot(before, current) || !safeToInitialize(current)) {
      Serial.println("ABORTADO: el almacenamiento cambio desde la inspeccion; revise antes de continuar.");
      return;
    }
    if (!initializeMissingSlots(current)) {
      return;
    }
  }

  const JournalInspection finalInspection = inspectAll();
  printInspection(finalInspection);
  if (finalInspection.slots[0].state != FILE_VALID
      || finalInspection.slots[1].state != FILE_VALID
      || !journalsCompatible(
          finalInspection.slots[0],
          finalInspection.slots[1])) {
    Serial.println("NO FINALIZADO: no se pudieron verificar ambas ranuras validas y compatibles.");
    return;
  }
  if (!queueUnchanged(before.queue, finalInspection.queue)) {
    Serial.println("NO FINALIZADO: /events.bin cambio durante la operacion; archivo no modificado por esta herramienta.");
    return;
  }
  Serial.println("INICIALIZACION VERIFICADA: ambas ranuras son validas; /events.bin no fue escrito.");
}

}

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println();
  Serial.println("Mantenimiento LittleFS del journal. Sin red, sensores ni escritura de eventos.");
  runMaintenance();
}

void loop() {
  delay(1000);
}
