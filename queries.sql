-- =====================================================================
-- queries.sql - parameterised queries the API endpoints will use.
-- ? = placeholder. ALWAYS bind values via parameters (prevents SQL injection).
-- =====================================================================

-- USERS / AUTH ---------------------------------------------------------
-- Register
INSERT INTO users (first_name, last_name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?, ?);
-- Login lookup (compare hash in backend code)
SELECT user_id, first_name, last_name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1;
-- Get one user (never return password_hash to the frontend)
SELECT user_id, first_name, last_name, email, phone, role, created_at FROM users WHERE user_id = ?;

-- PROFILES -------------------------------------------------------------
INSERT INTO clients (user_id, company_name, industry, website, location) VALUES (?, ?, ?, ?, ?);
INSERT INTO freelancers (user_id, title, bio, hourly_rate, location, availability) VALUES (?, ?, ?, ?, ?, ?);
SELECT * FROM v_freelancer_profiles;
SELECT * FROM v_freelancer_profiles WHERE freelancer_id = ?;
-- Search freelancers by skill
SELECT DISTINCT vf.* FROM v_freelancer_profiles vf
  JOIN freelancer_skills fs ON fs.freelancer_id = vf.freelancer_id
  JOIN skills s ON s.skill_id = fs.skill_id
 WHERE s.name = ?;

-- SKILLS ---------------------------------------------------------------
SELECT skill_id, name, category FROM skills ORDER BY name;
INSERT INTO skills (name, category) VALUES (?, ?);
INSERT INTO freelancer_skills (freelancer_id, skill_id, proficiency, years_experience) VALUES (?, ?, ?, ?);
DELETE FROM freelancer_skills WHERE freelancer_id = ? AND skill_id = ?;

-- JOBS -----------------------------------------------------------------
INSERT INTO jobs (client_id, title, description, budget_min, budget_max, deadline) VALUES (?, ?, ?, ?, ?, ?);
INSERT INTO job_skills (job_id, skill_id) VALUES (?, ?);
SELECT * FROM v_open_jobs ORDER BY created_at DESC;
SELECT * FROM jobs WHERE job_id = ?;
SELECT * FROM jobs WHERE client_id = ?;
UPDATE jobs SET title = ?, description = ?, budget_min = ?, budget_max = ?, deadline = ? WHERE job_id = ?;
UPDATE jobs SET status = 'cancelled' WHERE job_id = ?;
-- Search open jobs by keyword
SELECT * FROM v_open_jobs WHERE title LIKE '%' || ? || '%' OR description LIKE '%' || ? || '%';

-- PROPOSALS ------------------------------------------------------------
INSERT INTO proposals (job_id, freelancer_id, cover_letter, proposed_amount, estimated_days) VALUES (?, ?, ?, ?, ?);
SELECT * FROM v_proposals_detail WHERE job_id = ?;
SELECT * FROM v_proposals_detail WHERE freelancer_id = ?;
UPDATE proposals SET status = 'withdrawn' WHERE proposal_id = ? AND status = 'pending';
UPDATE proposals SET status = 'accepted'  WHERE proposal_id = ? AND status = 'pending';  -- trigger rejects the rest
UPDATE proposals SET status = 'rejected'  WHERE proposal_id = ? AND status = 'pending';

-- PROJECTS -------------------------------------------------------------
-- Create after accepting a proposal
INSERT INTO projects (job_id, proposal_id, client_id, freelancer_id, agreed_amount)
SELECT p.job_id, p.proposal_id, j.client_id, p.freelancer_id, p.proposed_amount
  FROM proposals p JOIN jobs j ON j.job_id = p.job_id
 WHERE p.proposal_id = ? AND p.status = 'accepted';
SELECT * FROM v_projects_detail WHERE client_id = ?;
SELECT * FROM v_projects_detail WHERE freelancer_id = ?;
UPDATE projects SET status = 'completed', end_date = date('now') WHERE project_id = ?;
