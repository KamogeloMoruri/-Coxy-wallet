-- =====================================================================
-- seed.sql - sample/test data. Run AFTER schema.sql
-- password_hash values are DUMMY placeholders, not real hashes.
-- Your backend must hash real passwords (bcrypt/argon2) before saving.
-- =====================================================================
PRAGMA foreign_keys = ON;

INSERT INTO users (first_name, last_name, email, password_hash, phone, role) VALUES
 ('Thabo',  'Mokoena', 'thabo@acmebuild.co.za',  'DUMMY_HASH_1', '0821234567', 'client'),
 ('Lerato', 'Dlamini', 'lerato@brightretail.co.za','DUMMY_HASH_2','0839876543', 'client'),
 ('Sipho',  'Nkosi',   'sipho.dev@example.com',   'DUMMY_HASH_3', '0711112222', 'freelancer'),
 ('Naledi', 'Khumalo', 'naledi.design@example.com','DUMMY_HASH_4','0723334444', 'freelancer'),
 ('Ayanda', 'Zulu',    'ayanda.data@example.com', 'DUMMY_HASH_5', '0745556666', 'freelancer'),
 ('Admin',  'User',    'admin@zonke.co.za',       'DUMMY_HASH_6', NULL,         'admin');

INSERT INTO clients (user_id, company_name, industry, website, location) VALUES
 (1, 'Acme Build',    'Construction', 'https://acmebuild.example',  'Johannesburg'),
 (2, 'Bright Retail', 'Retail',       'https://brightretail.example','Cape Town');

INSERT INTO freelancers (user_id, title, bio, hourly_rate, location, availability) VALUES
 (3, 'Full-Stack Developer', 'Builds web apps with Node and SQL.', 350.00, 'Pretoria',     'available'),
 (4, 'UI/UX Designer',       'Designs clean, usable interfaces.',   300.00, 'Durban',       'available'),
 (5, 'Data Analyst',         'Dashboards, SQL and reporting.',      280.00, 'Johannesburg', 'busy');

INSERT INTO skills (name, category) VALUES
 ('JavaScript', 'Development'), ('Node.js', 'Development'), ('SQL', 'Data'),
 ('HTML/CSS', 'Development'),   ('Figma', 'Design'),        ('UI Design', 'Design'),
 ('Data Analysis', 'Data'),     ('Power BI', 'Data');

INSERT INTO freelancer_skills (freelancer_id, skill_id, proficiency, years_experience) VALUES
 (1, 1, 'expert', 5), (1, 2, 'expert', 4), (1, 3, 'intermediate', 3), (1, 4, 'expert', 5),
 (2, 5, 'expert', 4), (2, 6, 'expert', 5), (2, 4, 'intermediate', 2),
 (3, 3, 'expert', 6), (3, 7, 'expert', 5), (3, 8, 'intermediate', 3);

INSERT INTO jobs (client_id, title, description, budget_min, budget_max, deadline, status) VALUES
 (1, 'Company website rebuild', 'Modern responsive website for our construction company.', 15000, 25000, '2026-11-30', 'open'),
 (1, 'Sales dashboard',         'Power BI dashboard for monthly project sales.',           8000, 12000, '2026-10-31', 'open'),
 (2, 'Online store UI design',  'Design mobile-first UI for our online store.',            10000, 18000, '2026-11-15', 'open');

INSERT INTO job_skills (job_id, skill_id) VALUES
 (1, 1), (1, 4), (1, 2),
 (2, 7), (2, 8), (2, 3),
 (3, 5), (3, 6);

INSERT INTO proposals (job_id, freelancer_id, cover_letter, proposed_amount, estimated_days, status) VALUES
 (1, 1, 'I can deliver a fast, responsive site using Node and modern CSS.', 20000, 30, 'pending'),
 (1, 2, 'I can design and build the front end with a strong UX focus.',    22000, 35, 'pending'),
 (2, 3, 'I will build an interactive Power BI dashboard from your data.',  10000, 14, 'pending'),
 (3, 2, 'I will deliver Figma designs for all key store screens.',         14000, 21, 'pending');
