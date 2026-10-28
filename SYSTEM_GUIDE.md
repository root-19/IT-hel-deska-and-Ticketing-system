# ICCT IT Help Desk & Ticketing System — System Guide

A web-based help desk where the ICCT community reports IT problems as **tickets**, and the IT Department assigns, fixes, and tracks them until they are resolved.

**Who uses it (5 account types):**

| Group | Role | What they do |
|---|---|---|
| Requesters (report problems) | Student, Faculty & Staff, Computer Lab | Submit tickets and follow them until fixed |
| IT Department (solve problems) | IT Technician | Works on the tickets assigned to them |
| IT Department (manages the system) | IT Admin | Assigns tickets, manages accounts, views reports |

**How a ticket flows:**

```
Requester submits  →  Admin assigns a technician  →  Technician works on it  →  Requester confirms & rates
   (Open)                  (Assigned)                  (In Progress → Resolved)        (Closed)
```

---

## 1. Landing Page (home page)

The first page anyone sees, even without an account. It introduces the system and leads people to log in or register.

**Navigation bar:** ICCT logo, links to Features, How It Works, Services, and FAQ, plus **Log in** and **Register** buttons. A logged-in user sees **Go to Dashboard** instead.

**Hero section:**
- Headline: *"Tech problems? We've got you covered."*
- Short description: students, faculty, and staff can report technical issues, track requests in real time, and get them resolved faster.
- Buttons: **Submit a Ticket** and **Track My Ticket**
- Quick facts: 24/7 availability, average response under 1 hour, all campuses
- A sample "Recent Tickets" card showing what tickets and statuses look like

**Features:** the four main benefits:
1. **Easy Ticket Submission:** report an issue in seconds with a guided form
2. **Real-Time Tracking:** see every status change (Open, In Progress, Resolved)
3. **Priority & Assignment:** tickets are prioritized and sent to the right technician
4. **Secure Accounts:** encrypted passwords and secure sessions

**How It Works:** 4 steps:
1. Create an account (ICCT ID number and email)
2. Submit a ticket (describe the issue, choose a category, set urgency)
3. Get assigned (an IT technician picks it up and gives updates)
4. Issue resolved (confirm the fix and rate the support)

**Support Areas (Services):** the four portals in the system: Student Support, Computer Laboratory Support, Faculty & Staff Support, and the IT Admin Dashboard.

**FAQ:** who can use the help desk, how fast tickets are answered, how to track a request, and what to do if you forget your password.

**Footer:** quick links and IT Department contact details (Mon–Sat, 8:00 AM – 5:00 PM, helpdesk@icct.edu.ph).

**ICCT AI Assistant (chatbot):** the floating sparkle button in the corner. It is available on the landing page, the login and register pages, and every page after login.
- Answers common IT problems (Wi-Fi, passwords, printers, projectors, school email, and more) with step-by-step fixes
- Replies in **English or Taglish**, depending on how the user writes
- Bases its answers on the system's **Knowledge Base** (the official ICCT procedures)
- If the problem can't be fixed by the steps, it tells the user to submit a ticket
- Never asks for passwords or OTP codes

> **Presenter note:** the "Recent Tickets" card and the "128 tickets resolved this week" number on the landing page are **sample display content**, not live data. Live numbers are on the Admin Dashboard and in Reports.

---

## 2. Logging In and Security

| | Student | Faculty & Staff | Computer Lab | IT Technician | IT Admin |
|---|---|---|---|---|---|
| How the account is made | **Registers on their own** | Created by Admin | Created by Admin | Created by Admin | Created by Admin |
| Login | Email or ID number + password | Email or ID number + password | Email or ID number + password | Email or ID number + password | Email or ID number + password |
| Email OTP code at login | ✅ Yes | ✅ Yes | ❌ No | ❌ No | ❌ No |

- **OTP (One-Time Password):** after entering the correct password, students and faculty receive a **6-digit code by email**. The code expires in **5 minutes**, allows 5 wrong tries, and can be resent every 60 seconds.
- **Forgot password:** every account can reset its password with a code sent to its email.
- **Login lockout:** after 5 wrong passwords, login is blocked for 5 minutes.
- **Password rule:** at least 8 characters with uppercase, lowercase, and a number.
- **Inactive accounts:** the Admin can deactivate an account, which immediately blocks it from logging in.

---

## 3. Student

**Portal name:** Student Support
**Account:** students create their own account on the **Register** page (ID number, first and last name, email, course, password, and agreement to the terms).

**Dashboard:**
- Greeting with a **Submit a Ticket** button
- **Virtual IT Office:** a live, animated office map where the IT team appears as robots (Working, Available, Away), and the student can follow where their ticket is
- Ticket counters: **Open**, **In Progress**, **Resolved**, **Total**
- **"What do you need help with?"**: shortcut cards for each ticket category
- **"Please confirm these fixes"**: tickets the IT team marked as resolved, waiting for the student's confirmation
- **Recent Tickets** list

**Ticket categories:**
| Category | Example |
|---|---|
| Student Portal Problem | Can't open grades, enrollment, or schedules |
| Login / Account Issue | Forgot password, locked account, login errors |
| Technical Assistance | Help with own device, campus Wi-Fi, school software |

**What a student can do:**
- Submit a ticket: category, subject, description, location (optional), and priority (Low, Medium, High, Urgent)
- **Attach up to 3 images** (screenshots or photos, 5 MB each)
- See **only their own tickets** and follow every status change
- Comment on their ticket to add details or ask for updates
- Receive **notifications** (bell icon) when their ticket is assigned, updated, or resolved, and an **email** when it is resolved
- When a ticket is resolved: **Confirm & Close** it with a **1–5 star rating** and feedback, or **Reopen** it if the problem is still there
- Update their contact details and change their password in **My Profile**

---

## 4. Faculty & Staff

**Portal name:** Faculty & Staff Support
**Account:** created by the IT Admin (faculty cannot self-register).

Faculty use the **same dashboard and ticket features as students**: Submit a Ticket, the Virtual IT Office, ticket counters, image attachments (up to 3), comments, notifications, and confirm/rate/reopen. Like students, they also need an **email OTP** to log in.

**What's different from a student:**
- The account is **verified by the IT Admin** instead of being self-registered
- Problems are about **teaching and school systems**, not a personal student account
- The location field asks for **Room / Office**, and the profile shows **Department / Office** instead of Course

**Ticket categories:**
| Category | Example |
|---|---|
| Online Class Problem | LMS, video calls, online class tools |
| School Systems Issue | Grading, attendance, other school systems |
| Email / Account Request | New school email, password reset, system access |
| Equipment Request | Projector, laptop, speakers |

---

## 5. Computer Laboratory (Lab)

**Portal name:** Computer Laboratory Support
**Account:** created by the IT Admin. The account belongs to a **laboratory room** (e.g. "Computer Laboratory 1"), not to one person, and is used by the lab in-charge.

Lab accounts use the same requester dashboard (Submit a Ticket, Virtual IT Office, counters, comments, notifications, confirm/rate/reopen).

**What's different:**
- **Location is required** on every ticket, and is pre-filled with the lab's name so the user only adds the PC number (e.g. "Lab 1, PC-12"). The technician knows exactly where to go.
- **No OTP** at login, only password
- No image attachment (that feature is for students and faculty)

**Ticket categories:**
| Category | Example |
|---|---|
| Broken / Defective Computer | Unit won't boot, damaged hardware, missing peripherals |
| Internet / Network Problem | No connection or slow internet in the lab |
| Printer Problem | Paper jams, no printout, low toner/ink |
| Software Installation Request | Install or update software needed for classes |

---

## 6. IT Technician

**Portal name:** IT Technician Workspace
**Account:** created by the IT Admin. Technicians **fix** problems; they do not submit tickets.

**Sidebar:** Dashboard, My Assigned Tickets (with a badge showing active tickets), Knowledge Base, My Profile.

**Dashboard:**
- Greeting with how many active tickets are in their queue
- Counters: **New Assignments**, **In Progress**, **On Hold**, **Resolved This Month**, and their **Average Rating** from requesters
- **My Queue**: active tickets, **most urgent first**
- **Recently Resolved** tickets

**What a technician can do, only on tickets assigned to them:**
- See the full ticket details, including the requester's email and any attached images
- Change the status to **In Progress**, **On Hold** (e.g. waiting for parts), or **Resolved**
  - Marking a ticket **Resolved** requires a note explaining the fix, and the requester is emailed automatically
- Change the ticket's **priority**
- Post **comments** to the requester, or **internal notes** that only IT staff can see
- Manage **Knowledge Base** articles used by the AI Assistant

**What a technician cannot do:** see tickets not assigned to them, assign tickets, close tickets, or reopen closed tickets.

---

## 7. IT Admin

**Portal name:** IT Admin Dashboard
**Account:** the main system account (no OTP at login). The Admin **runs the whole system**.

**Sidebar:** Dashboard, All Tickets, Unassigned (with a badge), Accounts, Reports, Knowledge Base, My Profile.

**Dashboard:**
- Alerts: how many tickets **need a technician** and how many **urgent** tickets are still open
- Quick buttons: **Assign Tickets**, **Create Account**, **Reports**
- Counters: **Total Tickets**, **Unassigned**, **Unresolved**, **Completed**, **Avg. Resolution Time**
- Charts: **new tickets per day** (last 30 days), **tickets by support area**, **tickets by status**
- **Unassigned Tickets** list
- **IT Personnel Workload**: each technician's active tickets, resolved tickets, and average rating

**Ticket management:**
- Sees **all tickets** from every portal, with filters for status, priority, category, support area, and search
- **Assigns or reassigns** a ticket to a technician. The list shows how many active tickets each technician already has, so work is shared fairly.
- Can set **any status**, including **Closed**, and is the **only one who can reopen** a closed ticket
- Can change priority, comment, and post internal notes on any ticket

**Accounts (user management):**
- **Create accounts** for Faculty & Staff, Computer Lab, IT Technician, and other Admins (a password can be auto-generated)
- **Edit** account details and role
- **Reset a password** (gives a temporary password to share privately)
- **Activate / Deactivate** an account
- Filter accounts by role and search by name, email, or ID

**Reports (IT Problems Report):**
- Date range: Last 7 days, This month, Last month, This year, or a custom range
- Summary: Tickets Submitted, Completed (with resolution rate), Unresolved, Unassigned, Avg. Resolution Time, **Satisfaction** (average rating)
- Breakdowns: new tickets over time, by support area, by priority, **problems by category**, **IT personnel performance**, and **locations with the most problems**
- **Export CSV** (for Excel) and **Print**

**Knowledge Base:**
- Add, edit, and hide help articles in **English and Tagalog**, with keywords
- The **AI Assistant answers from these articles**, so the Admin controls what advice it gives

---

## 8. Ticket Statuses

| Status | Meaning | Who sets it |
|---|---|---|
| Open | Just submitted, no technician yet | System (on submit) |
| Assigned | A technician has been assigned | Admin |
| In Progress | The technician is working on it | Technician / Admin |
| On Hold | Paused, e.g. waiting for parts or information | Technician / Admin |
| Resolved | Fixed; waiting for the requester to confirm | Technician / Admin |
| Closed | Confirmed by the requester (with rating), or closed by Admin | Requester / Admin |

**Priorities:** Low (minor, can wait) · Medium (affects my work) · High (can't work or class affected) · Urgent (many people affected)

Every change is recorded in the ticket's **Activity** timeline: who did what and when.

---

## 9. Features Shared by Everyone

- **Notifications:** bell icon with unread count; clicking one opens the related ticket
- **My Profile:** update contact details and change password
- **AI Assistant:** available on every page
- **Mobile-friendly:** works on phones, tablets, and computers

---

## 10. Suggested Demo Flow

**Before the presentation:** log in as Admin and create at least one **IT Technician** account in **Accounts**, so there is someone to assign tickets to. Use a real email inbox for the student you will register, so the OTP arrives.

1. Show the **Landing Page** and open the **AI Assistant** (ask: "walang internet sa lab").
2. **Register** a new student account → log in → enter the **OTP** from email.
3. As the student, **submit a ticket** with a screenshot attached.
4. Log in as **Admin** → see the new ticket under **Unassigned** → **assign** it to the technician.
5. Log in as the **Technician** → set it to **In Progress** → add an internal note → mark it **Resolved** with a note.
6. Back as the **student** → see the notification → **Confirm & Close** with a 5-star rating.
7. As **Admin** → show the **Dashboard** charts, **Reports**, and **Export CSV**.
8. As **Admin** → **create** a Faculty or Lab account in **Accounts**.
