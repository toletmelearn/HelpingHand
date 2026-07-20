# HelpingHand School ERP - Mobile App API Documentation

## Overview
This document describes the API endpoints available for the HelpingHand School ERP mobile application. The API uses RESTful architecture and follows standard HTTP response codes.

## Base URL
```
https://your-domain.com/api/v1
```

## Authentication
All protected endpoints require a valid Sanctum API token in the Authorization header:
```
Authorization: Bearer {token}
```

## Rate Limiting
All authenticated endpoints are limited to 60 requests per minute per IP address.
Public endpoints are limited to 10 requests per minute per IP address.

## Response Format
All responses follow this standard format:

### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": { ... },
    "timestamp": "2026-01-30T07:15:30.000000Z"
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": { ... },
    "timestamp": "2026-01-30T07:15:30.000000Z"
}
```

## Available Endpoints

### Authentication
- `POST /login` - Authenticate user and obtain API token
- `POST /logout` - Revoke API token
- `GET /user` - Get authenticated user details

### Student Dashboard Endpoints
#### Student Resource
- `GET /students` - Get all students
- `GET /students/{id}` - Get specific student
- `POST /students` - Create new student
- `PUT /students/{id}` - Update student
- `DELETE /students/{id}` - Delete student

#### Student-Specific Endpoints
- `GET /students/{id}/attendance` - Get student's attendance records
- `GET /students/{id}/results` - Get student's results/grades
- `GET /students/{id}/fees` - Get student's fee records

### Teacher Dashboard Endpoints
#### Teacher Resource
- `GET /teachers` - Get all teachers
- `GET /teachers/{id}` - Get specific teacher
- `POST /teachers` - Create new teacher
- `PUT /teachers/{id}` - Update teacher
- `DELETE /teachers/{id}` - Delete teacher

#### Teacher-Specific Endpoints
- `GET /teachers/{id}/classes` - Get teacher's assigned classes
- `GET /teachers/{id}/papers` - Get teacher's exam papers
- `GET /teachers/{id}/subject-classes` - Get teacher's subject and class assignments
- `GET /teachers/{id}/attendance-data` - Get teacher's attendance-related data
- `GET /teachers/{id}/grading-data` - Get teacher's grading and assessment data

### Parent/Guardian Dashboard Endpoints
#### Guardian Resource
- `GET /guardians` - Get all guardians
- `GET /guardians/{id}` - Get specific guardian
- `POST /guardians` - Create new guardian
- `PUT /guardians/{id}` - Update guardian
- `DELETE /guardians/{id}` - Delete guardian

#### Guardian-Specific Endpoints
- `GET /guardians/{id}/children` - Get guardian's children with progress data
- `GET /guardians/{id}/notifications` - Get guardian's notifications for their children

### Attendance Endpoints
#### Attendance Resource
- `GET /attendance` - Get all attendance records
- `GET /attendance/{id}` - Get specific attendance record
- `POST /attendance` - Create new attendance record
- `PUT /attendance/{id}` - Update attendance record
- `DELETE /attendance/{id}` - Delete attendance record

#### Attendance-Specific Endpoints
- `GET /attendance/student/{studentId}/monthly/{month}/{year}` - Monthly attendance report for student
- `GET /attendance/class/{classSection}/daily/{date}` - Daily attendance report for class
- `POST /attendance/bulk-mark` - Bulk mark attendance

### Exam Paper Endpoints
#### Exam Paper Resource
- `GET /exam-papers` - Get all exam papers
- `GET /exam-papers/{id}` - Get specific exam paper
- `POST /exam-papers` - Create new exam paper
- `PUT /exam-papers/{id}` - Update exam paper
- `DELETE /exam-papers/{id}` - Delete exam paper

#### Exam Paper-Specific Endpoints
- `POST /exam-papers/{id}/download` - Download exam paper
- `POST /exam-papers/{id}/toggle-publish` - Toggle exam paper publish status

### Bell Timing Endpoints
#### Bell Timing Resource
- `GET /bell-timing` - Get all bell timings
- `GET /bell-timing/{id}` - Get specific bell timing
- `POST /bell-timing` - Create new bell timing
- `PUT /bell-timing/{id}` - Update bell timing
- `DELETE /bell-timing/{id}` - Delete bell timing

#### Bell Timing-Specific Endpoints
- `GET /bell-timing/weekly/{classSection}` - Weekly timetable for class
- `GET /bell-timing/current-period` - Get current period
- `POST /bell-timing/bulk-create` - Bulk create bell timings

### Notification Endpoints
- `GET /notifications` - Get user's notifications
- `PUT /notifications/{id}/read` - Mark notification as read
- `PUT /notifications/mark-all-read` - Mark all notifications as read
- `GET /notifications/unread-count` - Get count of unread notifications

### Public Endpoints
- `GET /exam-papers/available/{classSection}` - Get available exam papers for class
- `POST /exam-papers/search` - Search exam papers
- `GET /bell-timing/today/{classSection}` - Get today's bell schedule for class

## Error Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Unprocessable Entity (Validation Error)
- `500` - Internal Server Error

## Example Usage

### Getting Student Attendance
```bash
curl -X GET \
  "https://your-domain.com/api/v1/students/1/attendance" \
  -H "Authorization: Bearer {your_api_token}" \
  -H "Accept: application/json"
```

### Getting Guardian's Children Progress
```bash
curl -X GET \
  "https://your-domain.com/api/v1/guardians/1/children" \
  -H "Authorization: Bearer {your_api_token}" \
  -H "Accept: application/json"
```

### Getting Teacher's Grading Data
```bash
curl -X GET \
  "https://your-domain.com/api/v1/teachers/1/grading-data" \
  -H "Authorization: Bearer {your_api_token}" \
  -H "Accept: application/json"
```

## Token Management
To create an API token for a user, you can use the following approach in your application:

```php
// In your login controller
$token = $user->createToken('Mobile App Token')->plainTextToken;
```

Tokens can be revoked either individually or all at once:
```php
// Revoke a specific token
$user->tokens()->where('id', $tokenId)->delete();

// Revoke all tokens for the user
$user->tokens()->delete();
```

## Security Best Practices
- Always use HTTPS for API requests
- Store tokens securely on the device
- Implement token refresh mechanisms
- Log out users when tokens are compromised
- Monitor API usage for unusual patterns