# Customer Requirements & System Specification: Integrated Institutional CMS

## 1. Executive Summary & Objective
We require a centralized Content Management System (CMS) to manage two distinct institutional web profiles under one unified dashboard[cite: 1]:
1. **Pondok Pesantren Web Profile**[cite: 1]
2. **Ma'had Aly (University) Web Profile**[cite: 1]

In addition to managing website content, the system must integrate:
- **Social Media API Management:** Official API publishing and scheduling for Instagram, Facebook, and TikTok across multiple accounts[cite: 1].
- **SIAKAD (Academic Information System) Integration:** Two-way sync for academic articles and a "Tanya Hukum" (Legal/Fiqh Consultation) module[cite: 1].
- **Granular Role-Based Access Control (RBAC):** Multi-level permissions based on user roles and site scopes[cite: 1].

---

## 2. Multi-Site Website Architecture

The CMS must support managing both websites from a single administrative panel without cross-contaminating content unless explicitly selected[cite: 1].

### A. Pondok Pesantren Profile
- **Modules:** Home, About, History, Vision & Mission, Leadership/Pengasuh, Facilities, Photo/Video Gallery, News, Announcements, Event Calendar, Admissions (PMB), Contact, FAQ, and SEO Controls.

### B. Ma'had Aly (University) Profile
- **Modules:** Home, About, History, Vision & Mission, Leadership/Mudir, Faculty/Lecturer Directory, Academic Programs (Takhassus), Curriculum, Research & Publications, Journals, Repository, Academic News, Admissions (PMB), Student Organizations (DEMA & UKM), and SEO Controls.

### C. Independent Site Configuration
Each website must have distinct configuration settings:
- Site Identity (Name, Logo, Favicon, Domain)
- both ui website profile idea is docs\req\ide.html(for mahad aly) and docs\req\pdf.html(for pondok)
- Custom Header, Footer, and Navigation Menus
- Analytics & Metadata Tracking Codes

---

## 3. Integrated Social Media Management (API-Based)

Must use official Graph/OpenAPIs (Instagram Graph API, Facebook Graph API, TikTok Content Posting API) with official OAuth token handling.

### Key Functional Requirements:
1. **Multi-Account Manager:** Connect and manage multiple social accounts (e.g., Pondok Official IG, Ma'had Aly Official IG, TikTok Accounts).
2. **Content Composer & Scheduler:** Write captions once, attach media (images/videos), set platform-specific tweaks (hashtags, character limits), and schedule or publish immediately.
3. **Multi-Account Queueing:** Background processing using job queues so that API failures on one account do not halt publishing on other accounts.
4. **Content Calendar:** Visual daily, weekly, and monthly calendar for drag-and-drop scheduling.

---

## 4. SIAKAD Two-Way Integration & "Tanya Hukum" Module

### A. Inbound Articles from SIAKAD
- Receive published academic articles and papers from SIAKAD via REST API webhooks[cite: 1].
- Admin approval workflow (`Draft` -> `Under Review` -> `Approved for Website Publication`).

### B. Two-Way "Tanya Hukum" Workflow
1. **Public Form:** Visitors submit questions via the public website (optionally anonymous).
2. **CMS Moderation:** CMS admin reviews incoming questions and forwards legitimate questions to SIAKAD via API.
3. **Academic Discussion (SIAKAD):** Scholars/Lecturers formulate and approve answers inside SIAKAD.
4. **Answer Return & Publication:** SIAKAD sends the finalized answer back to the CMS via Webhook. CMS admin reviews and publishes the question & answer to the public "Tanya Hukum" repository.

---

## 5. User Roles & Permission Matrix (RBAC)

The system requires granular permission control:
- **Superadmin:** Full system and server access.
- **Managing Editor:** Approves articles and publications across both sites.
- **Pondok Web Admin:** Manages Pondok pages, news, and galleries only.
- **Ma'had Aly Web Admin:** Manages Ma'had Aly pages, news, and academic materials only.
- **Social Media Manager:** Creates, schedules, and manages social media campaigns.
- **Tanya Hukum Administrator:** Moderates incoming questions and communicates with SIAKAD.

---

## 6. Database & System Requirements

- **Database:** PostgreSQL / MySQL with UUIDs for internal primary keys and `external_id` references for third-party systems.
- **Media Library:** Centralized asset management (images, videos, documents) supporting S3-compatible cloud storage.
- **Audit Logs:** Immutable activity logs tracking every creation, edit, deletion, and API sync attempt.