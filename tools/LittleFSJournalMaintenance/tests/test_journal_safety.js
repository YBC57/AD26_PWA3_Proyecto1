import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import test from "node:test";

const PENDING_MAGIC = 0x50454e44;
const PENDING_VERSION = 1;
const CHECKSUM_OFFSET = 84;
const RECORD_SIZE = 88;

function fnv1a(bytes) {
  let checksum = 2166136261;
  for (const byte of bytes) {
    checksum = Math.imul(checksum ^ byte, 16777619) >>> 0;
  }
  return checksum;
}

function inactiveRecord(sequence) {
  const record = Buffer.alloc(RECORD_SIZE);
  record.writeUInt32LE(PENDING_MAGIC, 0);
  record.writeUInt8(PENDING_VERSION, 4);
  record.writeUInt8(0, 5);
  record.writeUInt16LE(0, 6);
  record.writeUInt32LE(sequence >>> 0, 8);
  record.writeUInt32LE(fnv1a(record.subarray(0, CHECKSUM_OFFSET)), CHECKSUM_OFFSET);
  return record;
}

function validInactiveRecord(record) {
  return record.length === RECORD_SIZE
    && record.readUInt32LE(0) === PENDING_MAGIC
    && record.readUInt8(4) === PENDING_VERSION
    && record.readUInt8(5) === 0
    && record.readUInt32LE(CHECKSUM_OFFSET)
      === fnv1a(record.subarray(0, CHECKSUM_OFFSET));
}

function createExclusive(filePath, data) {
  const descriptor = fs.openSync(filePath, "wx");
  try {
    fs.writeFileSync(descriptor, data);
  } finally {
    fs.closeSync(descriptor);
  }
}

function withTemporaryDirectory(run) {
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), "journal-init-"));
  try {
    run(directory);
  } finally {
    fs.rmSync(directory, { recursive: true, force: true });
  }
}

test("inactive record layout and checksum validate; byte changes fail", () => {
  const record = inactiveRecord(0xffffffff);
  assert.equal(record.length, RECORD_SIZE);
  assert.equal(validInactiveRecord(record), true);

  const corrupted = Buffer.from(record);
  corrupted[12] ^= 0x01;
  assert.equal(validInactiveRecord(corrupted), false);
});

test("exclusive create refuses an existing path and preserves its bytes", () => {
  withTemporaryDirectory((directory) => {
    const filePath = path.join(directory, "pending0.bin");
    const original = Buffer.from("existing journal data");
    fs.writeFileSync(filePath, original);

    assert.throws(() => createExclusive(filePath, inactiveRecord(0)), {
      code: "EEXIST",
    });
    assert.deepEqual(fs.readFileSync(filePath), original);
  });
});

test("partial creation is detected and cannot be overwritten on retry", () => {
  withTemporaryDirectory((directory) => {
    const filePath = path.join(directory, "pending1.bin");
    const record = inactiveRecord(4);
    const descriptor = fs.openSync(filePath, "wx");
    fs.writeSync(descriptor, record, 0, 17);
    fs.closeSync(descriptor);

    const partial = fs.readFileSync(filePath);
    assert.deepEqual(partial, record.subarray(0, 17));
    assert.equal(validInactiveRecord(partial), false);
    assert.throws(() => createExclusive(filePath, record), {
      code: "EEXIST",
    });
    assert.deepEqual(fs.readFileSync(filePath), partial);
  });
});

test("missing slot sequence remains older across uint32 wraparound", () => {
  for (const existingSequence of [0, 1, 17, 0xffffffff]) {
    const missingSequence = (existingSequence - 1) >>> 0;
    const delta = (existingSequence - missingSequence) >>> 0;
    assert.equal(delta, 1);
  }
});
