# User guide

## 1. What this is

Portfolio OS is an ops app for multi-site content portfolios: projects & credential vault, day-to-day work (tasks, articles, links), people metrics, and finance (revenue, expenses, P&L, partner distributions). Permissions come from **roles** (a user can hold several; capabilities combine). Money is stored in minor units of your configured **base currency**; distributions are **manual only**. Times are stored in UTC and displayed in the timezone set in **Settings**.

## 2. Sign in

1. Open the app URL → **Sign in**.
2. Email + password. First successful login each day counts as **attendance check-in**.
3. Forgot password → email reset link (when mail is configured).

**Demo seed accounts** (local only — the seeder prints a random password anywhere else): password `password` for all  
`admin@example.com` · `partner@example.com` · `supervisor@example.com` · `staff@example.com` · `accountant@example.com`

---

## 3. By role

### Admin
**Can:** Everything (users, settings, templates, projects/vault, full work, people team views, full money).  
**Nav:** Home · Projects · Tasks · Articles · Links · Approvals · People* · Money* · Users · Settings · Task templates  

**Workflow**
1. Home — approvals / portfolio pulse / expiring credentials.
2. Users — create accounts, assign roles (multi-role OK).
3. Settings — org name, base currency & symbol, display timezone, late hour, credential alert windows.
4. Projects — create domains, set ownership (must total 100%), team, vault.
5. Task templates — default SEO checklist for new projects.
6. Approvals / Money as needed for governance.

### Partner
**Can:** View projects & work (read-ish), own people data, finance **read** + own partner statement. No user admin, no vault secrets manage by default, no distribution approve.  
**Nav:** Home · Projects · Tasks · Articles · Links · People (self) · Revenue · Expenses · P&L · Distributions · Partners (→ statement if no partners.view)

**Workflow**
1. Home — portfolio overview.
2. Projects — ownership & status snapshot.
3. Money → P&L / Distributions for the period.
4. Partners — open **statement** for capital, payouts, distribution credits.
5. Optional: Tasks/Articles/Links for visibility only.

### Supervisor
**Can:** Projects (update), full work create/assign/approve, credentials **view**, team people + mark leave/holiday. No money, no users/settings.  
**Nav:** Home · Projects · Tasks · Articles · Links · Approvals · Attendance · Work logs · Scorecard · Login history  

**Workflow**
1. Home — queue + team attendance today.
2. Approvals — j/k navigate, **a** approve, **r** reject.
3. Tasks — assign / bulk status; open task for evidence & comments.
4. Articles/Links — approve (creates expense when configured).
5. Attendance — mark leave/holiday for staff.
6. Scorecard / Work logs — review team output.

### Staff
**Can:** View projects; create/update own articles & links; update/submit tasks; own attendance, logs, scorecard, logins. No bulk assign, no money.  
**Nav:** Home · Projects · Tasks · Articles · Links · Attendance · Work logs · Scorecard · Login history  

**Workflow**
1. Sign in (check-in automatic).
2. Home / Tasks (**Mine only**) — start → complete → submit.
3. Articles — draft → submit for approval.
4. Links — log → submit.
5. Work logs — short daily note.
6. Scorecard — month review.

### Accountant
**Can:** View projects; full finance manage (revenue, expenses, P&L, distributions, partners/capital). No work queue, no people ops, no credentials workflow.  
**Nav:** Home · Projects · Revenue · Expenses · P&L · Distributions · Partners  

**Workflow**
1. Revenue — enter the month in the base or source currency (with its FX rate) or import CSV; export as needed.
2. Expenses — direct vs shared; receipt upload; mark paid / bulk paid.
3. P&L — pick month; check net by project.
4. Distributions — draft for month → open run → approve (locks) or void with reason.
5. Partners — capital/withdrawal entries; ledger export; statement views.

---

## 4. Shared features

| Feature | Who | Notes |
|---------|-----|--------|
| **Home** | All with access | Widgets depend on role (tasks, approvals, attendance, portfolio stats). |
| **Approvals** | Admin, Supervisor (+ any role with \*.approve) | Keyboard queue for tasks, article drafts, links. |
| **Attendance** | Staff+ who have permission | Check-in = first login that day; late after org “late hour”. |
| **Work logs / Scorecard** | Self (team if `people.view_team`) | Scorecard aggregates tasks/articles/links for the month. |
| **Projects → vault** | View/reveal per credentials.\* | Reveals are audited. |

---

## 5. Money basics

- **Staff / Supervisor:** n/a in nav.
- **Partner:** P&L, distributions (status), **Partners → statement**; CSV export where allowed. Do not edit approved distribution runs.
- **Accountant / Admin:** Revenue (source currency → base currency at a rate frozen per row), expenses (shared costs allocated on P&L by revenue), **manual** distribution draft → approve/void, partner capital ledger.

---

## 6. Three-click paths

| Goal | Path |
|------|------|
| Approve work | **Approvals** → review → Approve |
| My due work | **Home** → My tasks / **Tasks** → Mine only |
| Open a site vault | **Projects** → domain → Credentials |
| Month P&L | **P&L** → month control |
| Partner balance | **Partners** → statement (or open from partners list) |
| New distribution | **Distributions** → New draft → Open → Approve |
| Log link spend | **Links** → Log link → Submit → Approver |

\*People / Money section labels appear in the desktop sidebar only when you have any permission in that group.
