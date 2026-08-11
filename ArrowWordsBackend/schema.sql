CREATE DATABASE IF NOT EXISTS arrowwords
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE arrowwords;

-- A "pack" groups puzzles for a single sync unit (e.g. one newspaper issue,
-- or a themed bundle). Bump `version` any time you edit an already-published
-- pack's content — that's what tells installed apps to re-download it.
CREATE TABLE IF NOT EXISTS packs (
  id          VARCHAR(64) PRIMARY KEY,
  title       VARCHAR(255) NOT NULL,
  version     INT NOT NULL DEFAULT 1,
  updated_at  BIGINT NOT NULL,
  created_at  BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The grid itself (the word list that fully determines the puzzle) is
-- stored as a single JSON column, as requested — no per-letter/per-cell
-- relational tables. MySQL's native JSON type gives you JSON_EXTRACT/
-- JSON_TABLE if you ever want to query into it later.
CREATE TABLE IF NOT EXISTS puzzles (
  id          VARCHAR(64) PRIMARY KEY,
  pack_id     VARCHAR(64) NOT NULL,
  title       VARCHAR(255) NOT NULL,
  rows        INT NOT NULL,
  cols        INT NOT NULL,
  words_json  JSON NOT NULL,
  CONSTRAINT fk_puzzles_pack
    FOREIGN KEY (pack_id) REFERENCES packs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_puzzles_pack ON puzzles(pack_id);

-- Single-admin (or small team) login for the web GUI in /admin.
-- The installer creates the first row here — you never need to touch
-- this table by hand.
CREATE TABLE IF NOT EXISTS admin_users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(64) UNIQUE NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     BIGINT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
