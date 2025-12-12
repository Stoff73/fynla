# Feature Specification: Cross-Cutting Features - Administrator Panel

## Status: Live

## Executive Summary

The Administrator Panel provides system administration capabilities for managing users, database backups, and UK tax configuration. Administrators can create and manage user accounts, perform database backup and restore operations, and configure tax rates and allowances that drive all financial calculations throughout the application.

### Elevator Pitch

Complete system administration for user management, data protection through backups, and centralised UK tax configuration that powers all financial calculations.

### Problem Statement

The application requires administrative functions to manage users, protect data through backups, and maintain accurate UK tax rates. Without central tax configuration, rates would be hardcoded throughout the system and updates would be error-prone.

### Target Audience

- Primary: System administrators managing the Fynla instance
- Secondary: Support staff handling user issues
- Tertiary: Financial compliance staff updating tax configurations

### Unique Selling Proposition

Centralised tax configuration system with multi-year support, database-driven rates that can be updated without code changes, and comprehensive backup system for data protection.

### Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|-------------------|
| Backup frequency | Weekly minimum | Backup logs |
| Tax config accuracy | 100% match to HMRC | Annual audit |
| User management efficiency | Under 2 minutes per task | Task timing |
| System uptime | 99.5% | Monitoring |

---

## User Personas

### Persona 1: Admin - System Administrator

**Demographics**: Technical administrator managing Fynla deployment

**Goals**:
- Manage user accounts
- Maintain system backups
- Monitor system health

**Pain Points**:
- Needs reliable backup system
- Must handle user requests quickly
- Wants clear system status

**Success Criteria**: All admin tasks achievable, backups reliable, system stable.

### Persona 2: Finance - Tax Configuration Manager

**Demographics**: Financial professional maintaining tax rates

**Goals**:
- Update tax rates annually
- Ensure accuracy of calculations
- Maintain historical rates

**Pain Points**:
- Tax rules change annually
- Must update before new tax year
- Historical accuracy required

**Success Criteria**: Tax rates updated promptly, all calculations use correct values.

### Persona 3: Support - User Support Staff

**Demographics**: Support team handling user issues

**Goals**:
- Create accounts when needed
- Reset user access
- View user information

**Pain Points**:
- User password issues
- Account creation requests
- Quick resolution needed

**Success Criteria**: User issues resolved quickly, accounts managed efficiently.

---

## User Stories

### US-01: Access Admin Dashboard

**As an** administrator,
**I want to** access the admin panel,
**So that I** can perform administrative tasks.

**Acceptance Criteria**:
- Given I have admin privileges
- When I navigate to admin panel
- Then I see admin dashboard

### US-02: View System Statistics

**As an** administrator,
**I want to** see system statistics,
**So that I** understand system usage.

**Acceptance Criteria**:
- Given I am on admin dashboard
- When I view statistics
- Then I see key metrics

**Statistics Display**:
- Total number of users
- Records per module
- System health indicators

### US-03: View All Users

**As an** administrator,
**I want to** see all user accounts,
**So that I** can manage them.

**Acceptance Criteria**:
- Given I am on User Management tab
- When I view user list
- Then I see all users

**User Display**:
- Name
- Email
- Registration date
- Admin status
- Last login

### US-04: Create User Account

**As an** administrator,
**I want to** create new user accounts,
**So that** users can access the system.

**Acceptance Criteria**:
- Given I am on User Management
- When I create new user
- Then account is created

**Required Fields**:
- Full name
- Email address
- Password (or generate)
- Admin status

### US-05: Edit User Account

**As an** administrator,
**I want to** edit user accounts,
**So that I** can update information.

**Acceptance Criteria**:
- Given I have a user selected
- When I edit their details
- Then changes are saved

### US-06: Delete User Account

**As an** administrator,
**I want to** delete user accounts,
**So that** inactive accounts are removed.

**Acceptance Criteria**:
- Given I have a user selected
- When I delete and confirm
- Then account is removed

### US-07: Grant/Revoke Admin Access

**As an** administrator,
**I want to** manage admin privileges,
**So that** appropriate access is maintained.

**Acceptance Criteria**:
- Given I have a user selected
- When I toggle admin status
- Then privileges update

### US-08: Create Database Backup

**As an** administrator,
**I want to** create database backups,
**So that** data is protected.

**Acceptance Criteria**:
- Given I am on Database Backups tab
- When I click "Create Backup"
- Then backup is created and listed

**Backup Details**:
- Date and time
- File size
- Backup status

### US-09: View Available Backups

**As an** administrator,
**I want to** see all backups,
**So that I** can manage them.

**Acceptance Criteria**:
- Given I am on Database Backups
- When I view backup list
- Then I see all available backups

### US-10: Download Backup

**As an** administrator,
**I want to** download backups,
**So that I** have offline copies.

**Acceptance Criteria**:
- Given I have backups listed
- When I click download
- Then backup file downloads

### US-11: Restore from Backup

**As an** administrator,
**I want to** restore from backup,
**So that** I can recover data.

**Acceptance Criteria**:
- Given I have a backup selected
- When I click restore and confirm
- Then database is restored

**Warnings**:
- "This will overwrite current data"
- "Are you sure?"
- Requires confirmation

### US-12: Delete Old Backups

**As an** administrator,
**I want to** delete old backups,
**So that** storage is managed.

**Acceptance Criteria**:
- Given I have backups listed
- When I delete and confirm
- Then backup is removed

### US-13: View Tax Configuration

**As an** administrator,
**I want to** view tax settings,
**So that I** can verify accuracy.

**Acceptance Criteria**:
- Given I am on Tax Settings tab
- When I view configuration
- Then I see all tax settings

### US-14: Edit Tax Rates

**As an** administrator,
**I want to** update tax rates,
**So that** calculations use current values.

**Acceptance Criteria**:
- Given I am viewing tax category
- When I edit rates
- Then new rates are saved

### US-15: Create New Tax Year

**As an** administrator,
**I want to** create new tax year config,
**So that** new year rates are available.

**Acceptance Criteria**:
- Given current year config exists
- When I create new year
- Then can copy and modify

### US-16: Set Active Tax Year

**As an** administrator,
**I want to** set the active tax year,
**So that** system uses correct rates.

**Acceptance Criteria**:
- Given multiple years exist
- When I set active year
- Then calculations use that year

---

## Feature Details

### Admin Panel Structure

**Tabs**:
1. Dashboard - Overview statistics
2. User Management - User CRUD operations
3. Database Backups - Backup and restore
4. Tax Settings - Tax configuration

### User Management

**User Record Fields**:
| Field | Editable | Notes |
|-------|----------|-------|
| name | Yes | Full name |
| email | Yes | Must be unique |
| password | Reset only | Cannot view current |
| is_admin | Yes | Admin privileges |
| created_at | No | System managed |
| updated_at | No | System managed |

**Password Reset**:
- Admin can reset to new password
- User should change on next login
- No view of current password

### Database Backup System

**Backup Location**: `storage/app/backups/`

**Backup Format**: SQL dump file

**Backup Process**:
1. Admin initiates backup
2. System exports database to SQL
3. File saved with timestamp
4. Listed in admin panel

**Restore Process**:
1. Admin selects backup
2. Confirmation required
3. Current database replaced
4. Warning: Data loss for changes since backup

### Tax Configuration Categories

**Income Tax**:
- Personal allowance
- Basic rate band and rate
- Higher rate band and rate
- Additional rate threshold and rate
- Personal allowance taper

**National Insurance**:
- Primary threshold
- Upper earnings limit
- Employee rates
- Employer rates

**Capital Gains Tax**:
- Annual exemption
- Basic rate (residential and other)
- Higher rate (residential and other)

**Dividend Tax**:
- Dividend allowance
- Basic rate
- Higher rate
- Additional rate

**ISA Allowances**:
- Annual ISA limit
- Lifetime ISA limit
- Junior ISA limit

**Pension Allowances**:
- Annual allowance
- Money Purchase Annual Allowance
- Taper threshold income
- Taper adjusted income
- Minimum tapered allowance

**Inheritance Tax**:
- Nil Rate Band
- Residence Nil Rate Band
- RNRB taper threshold
- IHT rate
- Gift annual exemption
- Small gift exemption

**Stamp Duty (SDLT)**:
- Band thresholds
- Rates per band
- First-time buyer thresholds
- Additional property surcharge

### Tax Year Management

**Supported Years**:
- 2021/22
- 2022/23
- 2023/24
- 2024/25
- 2025/26 (current)

**Year Structure**: April 6 to April 5

**Creating New Year**:
1. Copy from previous year
2. Update changed values
3. Set as active when ready

**Active Year**:
- One year is "active"
- All calculations use active year rates
- Typically current tax year

---

## User Flows

### Flow 1: Create Database Backup

```
Admin Panel
    |
    v
Database Backups Tab
    |
    v
Click "Create Backup"
    |
    v
Backup process runs
    |
    v
Progress indicator
    |
    v
Backup complete
    |
    v
New backup appears in list
    |
    v
Download or leave stored
```

### Flow 2: Update Tax Rates for New Year

```
Admin Panel
    |
    v
Tax Settings Tab
    |
    v
Click "Create New Tax Year"
    |
    v
Enter year: 2026/27
    |
    v
Copy from 2025/26
    |
    v
Edit each category:
    - Income Tax
    - NI
    - ISA
    - Pensions
    - IHT
    - etc.
    |
    v
Save all changes
    |
    v
On April 6, set as active
```

### Flow 3: Create New User

```
Admin Panel
    |
    v
User Management Tab
    |
    v
Click "Create User"
    |
    v
Enter details:
    - Name
    - Email
    - Password
    - Admin status
    |
    v
Click "Create"
    |
    v
User account created
    |
    v
User can log in
```

---

## Edge Cases

### EC-01: Backup During High Usage

**Scenario**: Backup initiated during peak usage.
**Expected Behaviour**: Backup proceeds but may take longer. Consider scheduling off-peak.

### EC-02: Restore Fails

**Scenario**: Restore operation fails mid-way.
**Expected Behaviour**: Rollback if possible. Clear error message. May need manual intervention.

### EC-03: Invalid Tax Rate

**Scenario**: Admin enters invalid rate (e.g., 150%).
**Expected Behaviour**: Validation error. Reasonable range checks on percentages.

### EC-04: Delete Only Admin

**Scenario**: Admin tries to delete the only admin account.
**Expected Behaviour**: Prevent deletion. Must have at least one admin.

### EC-05: Email Already Exists

**Scenario**: Creating user with existing email.
**Expected Behaviour**: Validation error. Email must be unique.

### EC-06: Active Year Deletion

**Scenario**: Admin tries to delete active tax year.
**Expected Behaviour**: Prevent deletion. Must set different year as active first.

---

## Acceptance Criteria Summary

| ID | Criterion | Testable |
|----|-----------|----------|
| AC-01 | Admin dashboard shows statistics | Yes |
| AC-02 | Users can be listed | Yes |
| AC-03 | Users can be created | Yes |
| AC-04 | Users can be edited | Yes |
| AC-05 | Users can be deleted | Yes |
| AC-06 | Admin status can be toggled | Yes |
| AC-07 | Backups can be created | Yes |
| AC-08 | Backups can be downloaded | Yes |
| AC-09 | Database can be restored | Yes |
| AC-10 | Tax rates can be viewed and edited | Yes |
| AC-11 | New tax year can be created | Yes |
| AC-12 | Active tax year can be set | Yes |

---

## Dependencies

### Upstream Dependencies

- Database access
- File storage for backups
- Admin authentication/authorization

### Downstream Dependencies

- All financial calculations (use tax config)
- User authentication
- System reliability

---

## Technical Constraints

1. **Backup Size**: Large databases may take time
2. **Restore Downtime**: System unavailable during restore
3. **Tax Config Caching**: May need cache clear after updates
4. **Admin Authorization**: Must verify admin status on all actions

---

## Non-Functional Requirements

### Performance

- Backup creation: Under 5 minutes
- User list load: Under 2 seconds
- Tax config load: Under 1 second

### Security

- Admin actions logged
- Password hashing (never stored plain)
- Authorization checks on all endpoints

### Reliability

- Backup integrity verification
- Transaction safety for restore
- Config validation before save

---

## UX Considerations

1. **Clear Navigation**: Tabs for each function area
2. **Confirmation Dialogs**: Destructive actions require confirmation
3. **Progress Feedback**: Long operations show progress
4. **Error Messages**: Clear, actionable error messages
5. **Success Feedback**: Confirm successful operations
6. **Tax Config Organisation**: Logical grouping of related rates
7. **Backup List**: Clear date/time and size display
8. **User Search**: Filter/search user list
