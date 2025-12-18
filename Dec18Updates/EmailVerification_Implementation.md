# Email Verification Implementation

## Overview
Added 6-digit email verification codes for both registration and login flows. Codes expire every 60 seconds and can be auto-resent up to 2 times per session.

## Features
- **6-digit codes** sent via email from `noreply@fynla.org`
- **60-second expiry** with automatic countdown
- **Auto-resend** up to 2 times, then requires session refresh
- **Preview mode bypass** - demo users skip verification entirely
- **Responsive modal** with individual digit inputs and paste support

---

## Files Created

| File | Description |
|------|-------------|
| `database/migrations/2025_12_18_162231_create_email_verification_codes_table.php` | Migration for verification codes table |
| `app/Models/EmailVerificationCode.php` | Eloquent model with validation methods |
| `app/Mail/VerificationCode.php` | Mailable class for sending codes |
| `resources/views/emails/verification-code.blade.php` | Email template with prominent code display |
| `resources/js/components/Auth/VerificationCodeModal.vue` | Vue modal component |

## Files Modified

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/AuthController.php` | Added verification logic to register/login, new verifyCode/resendCode methods |
| `routes/api.php` | Added `/auth/verify-code` and `/auth/resend-code` routes |
| `resources/js/views/Login.vue` | Added verification modal integration |
| `resources/js/views/Register.vue` | Added verification modal integration |
| `resources/js/services/authService.js` | Added verifyCode and resendCode methods |

---

## API Endpoints

### POST /api/auth/verify-code
Verify a 6-digit code and return auth token.

**Request:**
```json
{
  "user_id": 123,
  "code": "123456",
  "type": "login"  // or "registration"
}
```

**Response (success):**
```json
{
  "success": true,
  "message": "Verification successful",
  "data": {
    "user": { ... },
    "access_token": "...",
    "token_type": "Bearer"
  }
}
```

### POST /api/auth/resend-code
Resend verification code (max 2 resends per session).

**Request:**
```json
{
  "user_id": 123,
  "type": "login"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Verification code sent",
  "data": {
    "resend_count": 1,
    "can_resend": true,
    "remaining_resends": 1
  }
}
```

---

## Database Schema

```sql
CREATE TABLE email_verification_codes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT FOREIGN KEY REFERENCES users(id) ON DELETE CASCADE,
    code VARCHAR(6),
    type VARCHAR(255),  -- 'registration' or 'login'
    resend_count INT DEFAULT 0,
    expires_at TIMESTAMP,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX (user_id, type, code)
);
```

---

## Flow Diagrams

### Login Flow
```
User enters credentials
        ↓
Backend validates password
        ↓
Is preview user? ─── YES ──→ Return token immediately
        │
       NO
        ↓
Generate 6-digit code
        ↓
Send email via Mail::to()
        ↓
Return { requires_verification: true, user_id, masked_email }
        ↓
Frontend shows VerificationCodeModal
        ↓
User enters code → POST /verify-code
        ↓
Backend validates code, marks as verified
        ↓
Return auth token
        ↓
Frontend stores token, navigates to dashboard
```

### Code Expiry Flow
```
Timer starts (60 seconds)
        ↓
Timer expires
        ↓
resend_count < 2? ─── YES ──→ Auto-resend code, reset timer
        │
       NO
        ↓
Show "Session expired, please refresh" message
```

---

## Email Template

The email uses the existing Fynla email styling with:
- Indigo header (`#4F46E5`)
- Large, prominent code display in monospace font
- 60-second expiry warning (amber)
- Security tip (green)
- Responsive design

---

## Testing

1. **Registration**: Register a new user → modal appears → enter code → redirects to dashboard
2. **Login**: Login with real user → modal appears → enter code → redirects to dashboard
3. **Preview mode**: Login as preview persona → no modal, direct dashboard
4. **Resend limit**: Wait for code to expire 3 times → shows "session expired" message
5. **Invalid code**: Enter wrong code → shows error, clears inputs

---

## Email Configuration

For the emails to actually send, configure in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<username>
MAIL_PASSWORD=<password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@fynla.org
MAIL_FROM_NAME="Fynla"
```

For local development, use Mailtrap or similar service, or set `MAIL_MAILER=log` to log emails instead of sending.
