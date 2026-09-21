"""
test_database.py - builds the database from schema.sql + seed.sql and
checks that keys, constraints, triggers and views all behave correctly.
Run:  python3 test_database.py      (needs only Python 3, no installs)
"""
import sqlite3, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
DB = os.path.join(HERE, "zonke.db")
if os.path.exists(DB):
    os.remove(DB)

con = sqlite3.connect(DB)
con.execute("PRAGMA foreign_keys = ON")
for f in ("schema.sql", "seed.sql"):
    con.executescript(open(os.path.join(HERE, f)).read())
con.execute("PRAGMA foreign_keys = ON")

passed = failed = 0
def ok(name, cond):
    global passed, failed
    if cond: passed += 1; print("PASS ", name)
    else:    failed += 1; print("FAIL ", name)

def must_fail(name, sql, params=()):
    try:
        con.execute(sql, params); con.rollback(); ok(name, False)
    except sqlite3.DatabaseError:
        con.rollback(); ok(name, True)

q = lambda sql, p=(): con.execute(sql, p).fetchall()

# --- structure & seed data
tables = {r[0] for r in q("SELECT name FROM sqlite_master WHERE type='table'")}
ok("all 9 tables exist", {"users","clients","freelancers","skills","freelancer_skills",
                          "jobs","job_skills","proposals","projects"} <= tables)
ok("foreign key check clean", q("PRAGMA foreign_key_check") == [])
ok("seed: 6 users",       q("SELECT COUNT(*) FROM users")[0][0] == 6)
ok("seed: 3 jobs open",   q("SELECT COUNT(*) FROM v_open_jobs")[0][0] == 3)
ok("view freelancer skills", "Node.js" in q("SELECT skills FROM v_freelancer_profiles WHERE freelancer_id=1")[0][0])

# --- constraints that must REJECT bad data
must_fail("duplicate email rejected",
    "INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES('A','B','THABO@acmebuild.co.za','x','client')")
must_fail("invalid email rejected",
    "INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES('A','B','notanemail','x','client')")
must_fail("invalid role rejected",
    "INSERT INTO users(first_name,last_name,email,password_hash,role) VALUES('A','B','a@b.co','x','boss')")
must_fail("client profile on freelancer user rejected",
    "INSERT INTO clients(user_id) VALUES(3)")
must_fail("orphan job (bad client) rejected",
    "INSERT INTO jobs(client_id,title,description) VALUES(999,'t','d')")
must_fail("budget_min > budget_max rejected",
    "INSERT INTO jobs(client_id,title,description,budget_min,budget_max) VALUES(1,'t','d',500,100)")
must_fail("duplicate proposal (same job+freelancer) rejected",
    "INSERT INTO proposals(job_id,freelancer_id,cover_letter,proposed_amount) VALUES(1,1,'again',100)")
must_fail("negative proposal amount rejected",
    "INSERT INTO proposals(job_id,freelancer_id,cover_letter,proposed_amount) VALUES(2,1,'x',-5)")
must_fail("duplicate skill (case-insensitive) rejected",
    "INSERT INTO skills(name) VALUES('javascript')")

# --- business workflow
con.execute("UPDATE proposals SET status='accepted' WHERE proposal_id=1"); con.commit()
ok("accept proposal -> other proposal on job rejected",
   q("SELECT status FROM proposals WHERE proposal_id=2")[0][0] == "rejected")
ok("accept proposal -> job in_progress",
   q("SELECT status FROM jobs WHERE job_id=1")[0][0] == "in_progress")
must_fail("cannot bid on non-open job",
    "INSERT INTO proposals(job_id,freelancer_id,cover_letter,proposed_amount) VALUES(1,3,'late',100)")
must_fail("project from non-accepted proposal rejected",
    "INSERT INTO projects(job_id,proposal_id,client_id,freelancer_id,agreed_amount) VALUES(2,3,1,3,10000)")

con.execute("""INSERT INTO projects (job_id, proposal_id, client_id, freelancer_id, agreed_amount)
               SELECT p.job_id, p.proposal_id, j.client_id, p.freelancer_id, p.proposed_amount
                 FROM proposals p JOIN jobs j ON j.job_id=p.job_id
                WHERE p.proposal_id=1 AND p.status='accepted'""")
con.commit()
ok("project created from accepted proposal", q("SELECT COUNT(*) FROM projects")[0][0] == 1)
ok("project view shows names",
   q("SELECT freelancer_name FROM v_projects_detail")[0][0] == "Sipho Nkosi")

con.execute("UPDATE projects SET status='completed', end_date=date('now') WHERE project_id=1"); con.commit()
ok("completing project closes job", q("SELECT status FROM jobs WHERE job_id=1")[0][0] == "closed")

# --- cascade delete
con.execute("DELETE FROM users WHERE user_id=5"); con.commit()
ok("deleting user cascades to freelancer profile + skills",
   q("SELECT COUNT(*) FROM freelancers WHERE user_id=5")[0][0] == 0 and
   q("SELECT COUNT(*) FROM freelancer_skills WHERE freelancer_id=3")[0][0] == 0)

print(f"\n{passed} passed, {failed} failed")
con.close()
sys.exit(1 if failed else 0)
