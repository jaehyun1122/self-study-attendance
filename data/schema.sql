CREATE TABLE IF NOT EXISTS attendance (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  student_no TEXT NOT NULL,
  name TEXT NOT NULL,
  attend_date TEXT NOT NULL,
  created_at TEXT NOT NULL,
  UNIQUE(student_no, attend_date)
);

CREATE TABLE IF NOT EXISTS admin (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admin_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL,
  expired_at TEXT NOT NULL
);
