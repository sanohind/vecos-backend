# Test Superadmin API

## Setup
1. Pastikan server Laravel berjalan: `php artisan serve`
2. Gunakan Postman atau curl untuk testing

## Step 1: Login sebagai Superadmin

### Request
```bash
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
    "email": "superadmin@example.com",
    "password": "password"
}
```

### Expected Response
```json
{
    "code": 200,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "Superadmin User",
            "email": "superadmin@example.com",
            "roles": ["Superadmin"],
            "permissions": ["view vehicles", "create vehicles", ...]
        },
        "token": "1|abc123...",
        "token_type": "Bearer"
    }
}
```

## Step 2: Test User Management Endpoints

### 2.1 Get Available Roles
```bash
GET http://localhost:8000/api/users/roles
Authorization: Bearer {token}
```

### 2.2 Get Available Departments
```bash
GET http://localhost:8000/api/users/departments
Authorization: Bearer {token}
```

### 2.3 List All Users
```bash
GET http://localhost:8000/api/users
Authorization: Bearer {token}
```

### 2.4 Create New Admin User
```bash
POST http://localhost:8000/api/users
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "New Admin User",
    "email": "admin2@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "department": "IT",
    "nik": "111222333",
    "roles": ["Admin"]
}
```

### 2.5 Create Regular User
```bash
POST http://localhost:8000/api/users
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Regular User",
    "email": "user2@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "department": "Marketing",
    "nik": "444555666",
    "roles": ["User"]
}
```

### 2.6 Get User Detail
```bash
GET http://localhost:8000/api/users/{user_id}
Authorization: Bearer {token}
```

### 2.7 Update User
```bash
PUT http://localhost:8000/api/users/{user_id}
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Updated User Name",
    "department": "Sales",
    "roles": ["User"]
}
```

### 2.8 Delete User
```bash
DELETE http://localhost:8000/api/users/{user_id}
Authorization: Bearer {token}
```

## Step 3: Test Admin Capabilities

### 3.1 Create Vehicle (as Superadmin)
```bash
POST http://localhost:8000/api/vehicles
Authorization: Bearer {token}
Content-Type: application/json

{
    "name": "Test Vehicle",
    "type": "Car",
    "plate_number": "B 1234 ABC",
    "capacity": 4,
    "status": "available"
}
```

### 3.2 Approve Booking (as Superadmin)
```bash
POST http://localhost:8000/api/bookings/{booking_id}/approve
Authorization: Bearer {token}
```

## Expected Results

### Success Cases
- ✅ Superadmin bisa login
- ✅ Superadmin bisa mengakses semua endpoint user management
- ✅ Superadmin bisa create, read, update, delete users
- ✅ Superadmin bisa assign roles
- ✅ Superadmin bisa mengakses semua fitur admin

### Security Tests
- ✅ User tanpa role Superadmin tidak bisa akses user management
- ✅ Superadmin tidak bisa delete dirinya sendiri
- ✅ Validasi data berfungsi dengan baik

## Troubleshooting

### Common Issues
1. **403 Forbidden**: Pastikan user memiliki role Superadmin
2. **422 Validation Error**: Periksa data yang dikirim
3. **404 Not Found**: Periksa URL endpoint
4. **Token Expired**: Login ulang untuk mendapatkan token baru

### Debug Steps
1. Check user roles: `GET /api/auth/me`
2. Verify permissions: Check response dari login
3. Check middleware: Pastikan route protection aktif
4. Check database: Pastikan role dan permission tersimpan

## Test Script (curl)

```bash
#!/bin/bash

# Login
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@example.com","password":"password"}' \
  | jq -r '.data.token')

echo "Token: $TOKEN"

# Test endpoints
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/users
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/users/roles
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/users/departments
```
