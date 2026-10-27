# ESP8266 LittleFS journal maintenance

This is a standalone Arduino sketch for inspecting and initializing only
`/pending0.bin` and `/pending1.bin`. It does not include or modify the main
firmware or its configuration.

## Safety behavior

- Mounts LittleFS with `LittleFSConfig(false)`, so a mount error cannot trigger
  automatic formatting.
- Inspects `/events.bin` and both journal slots using `statPath()` return codes.
  Only `LFS_ERR_NOENT` means a path is verifiably absent; any other query error
  is not treated as absence.
- Reads `/events.bin` without opening it for writing. The existing queue format
  has no checksum field or checksum algorithm; the sketch checks its exact
  size, magic, version, count, and used event structures, and reports that no
  queue checksum is defined.
- Validates the journal record size, magic, version, active flag, event
  structure when active, and the firmware's FNV-1a checksum.
- Refuses initialization if `/events.bin` or either slot is damaged or
  unverifiable, or if existing journal sequences are ambiguous.
- Requires the exact serial line `INICIALIZAR JOURNAL` before any write.
- Rechecks the inspected snapshot after confirmation. Every target is checked
  again for `LFS_ERR_NOENT`, then written through `writeExclusive()`, which
  uses `LFS_O_WRONLY | LFS_O_CREAT | LFS_O_EXCL` and returns the actual errors
  from open, write, sync, and close. Existing files cannot be overwritten by
  this operation; any error, short write, or failed verification stops the run
  and leaves the file as-is.
- If exactly one slot is absent, its inactive record gets the existing
  sequence minus one (with uint32 wraparound), so the existing record remains
  newest, including when it contains an active transition. If both slots are
  absent, the new sequence values are 0 and 1.
- Reads both slots again and only reports success if both are valid and
  sequence-compatible. It never removes, truncates, renames, repairs, or writes
  `/events.bin`.

After a successful write-and-sync result, the sketch rereads each complete
record and verifies its structure, checksum, state, and sequence. A failed or
partial read is not success.

## Installed Core 3.1.2 extension

The installed Core reports version 3.1.2, but it is not the unmodified upstream
3.1.2 source: the local installation already contains the following extension
in these five core files. Upstream tag `3.1.2` does not expose these interfaces.
This task does not edit the installed core.

1. `cores/esp8266/FSImpl.h`: virtual `openExclusive()`, `writeExclusive()`,
   and `statPath()` hooks, with unsupported defaults for other filesystems.
2. `cores/esp8266/FS.h`: public declarations for those three operations.
3. `cores/esp8266/FS.cpp`: wrappers forwarding the operations to `FSImpl` and
   returning the exclusive-write byte count and status.
4. `libraries/LittleFS/src/LittleFS.h`: LittleFS overrides for all three
   operations.
5. `libraries/LittleFS/src/LittleFS.cpp`: `openExclusive()` uses
   `LFS_O_WRONLY | LFS_O_CREAT | LFS_O_EXCL`; `writeExclusive()` uses the same
   flags and propagates the actual `lfs_file_open`, `lfs_file_write`,
   `lfs_file_sync`, and `lfs_file_close` status; `statPath()` returns the
   original `lfs_stat()` status and file metadata.

These are additions around `FS.open()`, which remains unchanged. Keep this
extension when using the sketch; a pristine upstream 3.1.2 installation will
not compile it. Back up the core before replacing or updating the installed
package.

## Build and use

From the repository root in PowerShell:

```powershell
$cli = "$env:LOCALAPPDATA\Programs\Arduino IDE\resources\app\lib\backend\resources\arduino-cli.exe"
& $cli compile --fqbn esp8266:esp8266:nodemcuv2 --warnings all .\tools\LittleFSJournalMaintenance
```

Open the sketch directory in Arduino IDE and select **NodeMCU 1.0 (ESP-12E
Module)**. Upload only after reviewing the diff and build output. On the board,
open Serial Monitor at 115200 baud, inspect the three reported paths, and type
`INICIALIZAR JOURNAL` only if every displayed status is understood. This
document is not a request to upload or run the tool; perform the physical check
separately before proceeding with firmware tests.

## Isolated host tests

Run the standard-library tests with:

```powershell
node .\tools\LittleFSJournalMaintenance\tests\test_journal_safety.js
```

They use temporary host files to check the record checksum and the
create-exclusive/no-overwrite and partial-write stop behavior. They are not a
LittleFS emulator and do not constitute a physical NodeMCU test.
