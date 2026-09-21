-- =====================================================================
-- ZONKE REDEVELOPMENT PROJECT - TEAM 4 (Backend & Database)
-- Database: SQLite 3 (file-based, no server needed)
-- File: schema.sql  -> creates all tables, keys, constraints, indexes
-- =====================================================================
PRAGMA foreign_keys = ON;

-- Drop in reverse dependency order so the script can be re-run safely
DROP VIEW  IF EXISTS v_open_jobs;
DROP VIEW  IF EXISTS v_freelancer_profiles;
DROP VIEW  IF EXISTS v_proposals_detail;
DROP VIEW  IF EXISTS v_projects_detail;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS proposals;
DROP TABLE IF EXISTS job_skills;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS freelancer_skills;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS freelancers;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
-- USERS: one row per login account (shared by clients and freelancers)
-- password_hash must hold a bcrypt/argon2 hash, NEVER a plain password
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id        INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name     TEXT    NOT NULL CHECK (length(trim(first_name)) > 0),
    last_name      TEXT    NOT NULL CHECK (length(trim(last_name)) > 0),
    email          TEXT    NOT NULL UNIQUE COLLATE NOCASE
                           CHECK (email LIKE '%_@_%._%'),
    password_hash  TEXT    NOT NULL,
    phone          TEXT,
    role           TEXT    NOT NULL CHECK (role IN ('client', 'freelancer', 'admin')),
    is_active      INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at     TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ---------------------------------------------------------------------
-- CLIENTS: extra details for users whose role = 'client' (1-to-1 with users)
-- ---------------------------------------------------------------------
CREATE TABLE clients (
    client_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id       INTEGER NOT NULL UNIQUE,
    company_name  TEXT,
    industry      TEXT,
    website       TEXT,
    location      TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- FREELANCERS: extra details for users whose role = 'freelancer' (1-to-1)
-- ---------------------------------------------------------------------
CREATE TABLE freelancers (
    freelancer_id  INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL UNIQUE,
    title          TEXT,
    bio            TEXT,
    hourly_rate    REAL CHECK (hourly_rate IS NULL OR hourly_rate >= 0),
    location       TEXT,
    availability   TEXT NOT NULL DEFAULT 'available'
                   CHECK (availability IN ('available', 'busy', 'unavailable')),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- SKILLS: master list of skills (no duplicates)
-- ---------------------------------------------------------------------
CREATE TABLE skills (
    skill_id  INTEGER PRIMARY KEY AUTOINCREMENT,
    name      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    category  TEXT
);

-- FREELANCER_SKILLS: many-to-many (freelancer <-> skill)
CREATE TABLE freelancer_skills (
    freelancer_id      INTEGER NOT NULL,
    skill_id           INTEGER NOT NULL,
    proficiency        TEXT NOT NULL DEFAULT 'intermediate'
                       CHECK (proficiency IN ('beginner', 'intermediate', 'expert')),
    years_experience   INTEGER CHECK (years_experience IS NULL OR years_experience >= 0),
    PRIMARY KEY (freelancer_id, skill_id),
    FOREIGN KEY (freelancer_id) REFERENCES freelancers(freelancer_id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id)      REFERENCES skills(skill_id)           ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- JOBS: work posted by a client
-- ---------------------------------------------------------------------
CREATE TABLE jobs (
    job_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id    INTEGER NOT NULL,
    title        TEXT NOT NULL CHECK (length(trim(title)) > 0),
    description  TEXT NOT NULL,
    budget_min   REAL CHECK (budget_min IS NULL OR budget_min >= 0),
    budget_max   REAL CHECK (budget_max IS NULL OR budget_max >= 0),
    deadline     TEXT,                      -- ISO date: YYYY-MM-DD
    status       TEXT NOT NULL DEFAULT 'open'
                 CHECK (status IN ('open', 'in_progress', 'closed', 'cancelled')),
    created_at   TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now')),
    CHECK (budget_min IS NULL OR budget_max IS NULL OR budget_min <= budget_max),
    FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE
);

-- JOB_SKILLS: many-to-many (job <-> required skill)
CREATE TABLE job_skills (
    job_id    INTEGER NOT NULL,
    skill_id  INTEGER NOT NULL,
    PRIMARY KEY (job_id, skill_id),
    FOREIGN KEY (job_id)   REFERENCES jobs(job_id)     ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(skill_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- PROPOSALS: a freelancer's bid on a job (one per freelancer per job)
-- ---------------------------------------------------------------------
CREATE TABLE proposals (
    proposal_id       INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id            INTEGER NOT NULL,
    freelancer_id     INTEGER NOT NULL,
    cover_letter      TEXT NOT NULL,
    proposed_amount   REAL NOT NULL CHECK (proposed_amount >= 0),
    estimated_days    INTEGER CHECK (estimated_days IS NULL OR estimated_days > 0),
    status            TEXT NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending', 'accepted', 'rejected', 'withdrawn')),
    submitted_at      TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (job_id, freelancer_id),
    FOREIGN KEY (job_id)        REFERENCES jobs(job_id)               ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES freelancers(freelancer_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- PROJECTS: created when a proposal is accepted (one project per proposal)
-- ---------------------------------------------------------------------
CREATE TABLE projects (
    project_id     INTEGER PRIMARY KEY AUTOINCREMENT,
    job_id         INTEGER NOT NULL UNIQUE,
    proposal_id    INTEGER NOT NULL UNIQUE,
    client_id      INTEGER NOT NULL,
    freelancer_id  INTEGER NOT NULL,
    agreed_amount  REAL NOT NULL CHECK (agreed_amount >= 0),
    start_date     TEXT NOT NULL DEFAULT (date('now')),
    end_date       TEXT,
    status         TEXT NOT NULL DEFAULT 'active'
                   CHECK (status IN ('active', 'completed', 'cancelled')),
    CHECK (end_date IS NULL OR end_date >= start_date),
    FOREIGN KEY (job_id)        REFERENCES jobs(job_id)               ON DELETE CASCADE,
    FOREIGN KEY (proposal_id)   REFERENCES proposals(proposal_id)     ON DELETE CASCADE,
    FOREIGN KEY (client_id)     REFERENCES clients(client_id)         ON DELETE CASCADE,
    FOREIGN KEY (freelancer_id) REFERENCES freelancers(freelancer_id) ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- INDEXES for the lookups the API will do most often
-- ---------------------------------------------------------------------
CREATE INDEX idx_users_role            ON users(role);
CREATE INDEX idx_jobs_client           ON jobs(client_id);
CREATE INDEX idx_jobs_status           ON jobs(status);
CREATE INDEX idx_proposals_job         ON proposals(job_id);
CREATE INDEX idx_proposals_freelancer  ON proposals(freelancer_id);
CREATE INDEX idx_projects_client       ON projects(client_id);
CREATE INDEX idx_projects_freelancer   ON projects(freelancer_id);
CREATE INDEX idx_freelancer_skills_sk  ON freelancer_skills(skill_id);
CREATE INDEX idx_job_skills_sk         ON job_skills(skill_id);

-- ---------------------------------------------------------------------
-- TRIGGERS
-- ---------------------------------------------------------------------
-- Keep updated_at current
CREATE TRIGGER trg_users_updated AFTER UPDATE ON users
FOR EACH ROW WHEN NEW.updated_at = OLD.updated_at
BEGIN
    UPDATE users SET updated_at = datetime('now') WHERE user_id = NEW.user_id;
END;

CREATE TRIGGER trg_jobs_updated AFTER UPDATE ON jobs
FOR EACH ROW WHEN NEW.updated_at = OLD.updated_at
BEGIN
    UPDATE jobs SET updated_at = datetime('now') WHERE job_id = NEW.job_id;
END;

-- A client profile may only be attached to a user whose role is 'client'
CREATE TRIGGER trg_clients_role_check BEFORE INSERT ON clients
FOR EACH ROW
WHEN (SELECT role FROM users WHERE user_id = NEW.user_id) <> 'client'
BEGIN
    SELECT RAISE(ABORT, 'User must have role client to have a client profile');
END;

-- A freelancer profile may only be attached to a user whose role is 'freelancer'
CREATE TRIGGER trg_freelancers_role_check BEFORE INSERT ON freelancers
FOR EACH ROW
WHEN (SELECT role FROM users WHERE user_id = NEW.user_id) <> 'freelancer'
BEGIN
    SELECT RAISE(ABORT, 'User must have role freelancer to have a freelancer profile');
END;

-- Proposals can only be submitted to open jobs
CREATE TRIGGER trg_proposals_open_job BEFORE INSERT ON proposals
FOR EACH ROW
WHEN (SELECT status FROM jobs WHERE job_id = NEW.job_id) <> 'open'
BEGIN
    SELECT RAISE(ABORT, 'Proposals can only be submitted to open jobs');
END;

-- When a proposal is accepted: reject the others, mark job in_progress
CREATE TRIGGER trg_proposal_accepted AFTER UPDATE OF status ON proposals
FOR EACH ROW WHEN NEW.status = 'accepted' AND OLD.status <> 'accepted'
BEGIN
    UPDATE proposals SET status = 'rejected'
     WHERE job_id = NEW.job_id AND proposal_id <> NEW.proposal_id AND status = 'pending';
    UPDATE jobs SET status = 'in_progress' WHERE job_id = NEW.job_id;
END;

-- A project must be built from an ACCEPTED proposal that matches its job and freelancer
CREATE TRIGGER trg_projects_valid BEFORE INSERT ON projects
FOR EACH ROW
WHEN NOT EXISTS (
    SELECT 1 FROM proposals p JOIN jobs j ON j.job_id = p.job_id
     WHERE p.proposal_id = NEW.proposal_id
       AND p.status = 'accepted'
       AND p.job_id = NEW.job_id
       AND p.freelancer_id = NEW.freelancer_id
       AND j.client_id = NEW.client_id)
BEGIN
    SELECT RAISE(ABORT, 'Project must come from an accepted proposal matching job, client and freelancer');
END;

-- When a project completes, close the job
CREATE TRIGGER trg_project_completed AFTER UPDATE OF status ON projects
FOR EACH ROW WHEN NEW.status = 'completed' AND OLD.status <> 'completed'
BEGIN
    UPDATE jobs SET status = 'closed' WHERE job_id = NEW.job_id;
END;

-- ---------------------------------------------------------------------
-- VIEWS (ready-made read queries for the API)
-- ---------------------------------------------------------------------
CREATE VIEW v_open_jobs AS
SELECT j.job_id, j.title, j.description, j.budget_min, j.budget_max, j.deadline,
       j.created_at, c.client_id,
       COALESCE(c.company_name, u.first_name || ' ' || u.last_name) AS client_name,
       (SELECT GROUP_CONCAT(s.name, ', ')
          FROM job_skills js JOIN skills s ON s.skill_id = js.skill_id
         WHERE js.job_id = j.job_id) AS required_skills,
       (SELECT COUNT(*) FROM proposals p WHERE p.job_id = j.job_id) AS proposal_count
  FROM jobs j
  JOIN clients c ON c.client_id = j.client_id
  JOIN users   u ON u.user_id   = c.user_id
 WHERE j.status = 'open';

CREATE VIEW v_freelancer_profiles AS
SELECT f.freelancer_id, u.user_id, u.first_name, u.last_name, u.email,
       f.title, f.bio, f.hourly_rate, f.location, f.availability,
       (SELECT GROUP_CONCAT(s.name, ', ')
          FROM freelancer_skills fs JOIN skills s ON s.skill_id = fs.skill_id
         WHERE fs.freelancer_id = f.freelancer_id) AS skills
  FROM freelancers f
  JOIN users u ON u.user_id = f.user_id
 WHERE u.is_active = 1;

CREATE VIEW v_proposals_detail AS
SELECT p.proposal_id, p.job_id, j.title AS job_title, p.freelancer_id,
       fu.first_name || ' ' || fu.last_name AS freelancer_name,
       p.proposed_amount, p.estimated_days, p.status, p.submitted_at, p.cover_letter
  FROM proposals p
  JOIN jobs        j  ON j.job_id        = p.job_id
  JOIN freelancers f  ON f.freelancer_id = p.freelancer_id
  JOIN users       fu ON fu.user_id      = f.user_id;

CREATE VIEW v_projects_detail AS
SELECT pr.project_id, pr.job_id, j.title AS job_title,
       pr.client_id,     cu.first_name || ' ' || cu.last_name AS client_name,
       pr.freelancer_id, fu.first_name || ' ' || fu.last_name AS freelancer_name,
       pr.agreed_amount, pr.start_date, pr.end_date, pr.status
  FROM projects pr
  JOIN jobs        j  ON j.job_id        = pr.job_id
  JOIN clients     c  ON c.client_id     = pr.client_id
  JOIN users       cu ON cu.user_id      = c.user_id
  JOIN freelancers f  ON f.freelancer_id = pr.freelancer_id
  JOIN users       fu ON fu.user_id      = f.user_id;
