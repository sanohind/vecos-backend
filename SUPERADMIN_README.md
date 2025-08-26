# Role Superadmin - Vehicle Backend System

## Overview
Role **Superadmin** adalah role tertinggi dalam sistem yang memiliki semua kemampuan Admin plus kemampuan untuk mengelola user (User Management).

## Fitur Superadmin

### 1. Semua Kemampuan Admin
- ✅ **Vehicle Management**: CRUD kendaraan
- ✅ **Booking Management**: Approve, reject, complete booking
- ✅ **View All Data**: Melihat semua data kendaraan dan booking

### 2. User Management (Khusus Superadmin)
- ✅ **Create User**: Membuat user baru dengan role tertentu
- ✅ **Read User**: Melihat daftar semua user dengan filter dan search
- ✅ **Update User**: Mengedit informasi user dan role
- ✅ **Delete User**: Menghapus user (dengan validasi keamanan)
- ✅ **Role Assignment**: Menetapkan role kepada user
- ✅ **Department Management**: Mengelola departemen user

## Endpoint User Management

### Authentication
Semua endpoint user management memerlukan:
- Bearer Token (Laravel Sanctum)
- Role: **Superadmin**

### Endpoints

#### 1. List Users
```
GET /api/users
```
**Query Parameters:**
- `search`: Search by name, email, nik, atau department
- `role`: Filter by role name
- `department`: Filter by department
- `per_page`: Items per page (default: 15)

**Example:**
```
GET /api/users?search=john&role=Admin&per_page=20
```

#### 2. Create User
```
POST /api/users
```
**Required Fields:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "department": "Marketing",
    "nik": "123456789",
    "roles": ["Admin", "User"]
}
```

#### 3. Get User Detail
```
GET /api/users/{id}
```

#### 4. Update User
```
PUT /api/users/{id}
PATCH /api/users/{id}
```
**Fields (optional):**
```json
{
    "name": "John Doe Updated",
    "email": "john.updated@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123",
    "department": "Sales",
    "nik": "987654321",
    "roles": ["User"]
}
```

#### 5. Delete User
```
DELETE /api/users/{id}
```

#### 6. Get Available Roles
```
GET /api/users/roles
```

#### 7. Get Available Departments
```
GET /api/users/departments
```

## Keamanan

### 1. Role Protection
- Hanya user dengan role **Superadmin** yang bisa mengakses endpoint user management
- Middleware `role:Superadmin` diterapkan pada semua route user management

### 2. Self-Protection
- Superadmin tidak bisa menghapus akunnya sendiri
- Sistem mencegah penghapusan superadmin terakhir

### 3. Validation
- Form Request validation untuk create dan update
- Custom error messages dalam Bahasa Indonesia
- Unique validation untuk email dan NIK

## Database Structure

### Roles
- **Superadmin**: Full access + User Management
- **Admin**: Vehicle & Booking Management
- **User**: Basic access (view vehicles, manage own bookings)

### Permissions
- Vehicle: view, create, update, delete
- Booking: view, create, update, delete, approve, reject
- User: view, create, update, delete

## Testing

### Login sebagai Superadmin
```
POST /api/auth/login
{
    "email": "superadmin@example.com",
    "password": "password"
}
```

### Test User Management
1. **Create Admin User:**
```bash
curl -X POST /api/users \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Admin",
    "email": "admin2@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "department": "IT",
    "nik": "111222333",
    "roles": ["Admin"]
  }'
```

2. **List Users:**
```bash
curl -X GET /api/users \
  -H "Authorization: Bearer {token}"
```

## Error Handling

### Common Errors
- **403 Forbidden**: User tidak memiliki role Superadmin
- **422 Validation Error**: Data tidak valid
- **404 Not Found**: User tidak ditemukan
- **409 Conflict**: Email/NIK sudah terdaftar

### Error Response Format
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["Email sudah terdaftar."],
        "nik": ["NIK sudah terdaftar."]
    }
}
```

## Best Practices

### 1. Role Assignment
- Selalu assign minimal satu role kepada user baru
- Gunakan role yang sesuai dengan kebutuhan user
- Hindari memberikan role Superadmin kepada user biasa

### 2. User Management
- Validasi data sebelum create/update
- Backup data sebelum delete
- Monitor aktivitas user management

### 3. Security
- Gunakan password yang kuat
- Rotate token secara berkala
- Monitor login attempts

## Migration & Seeding

### Run Seeder
```bash
php artisan db:seed --class=SuperadminSeeder
```

### Manual Role Creation
```php
// Create Superadmin role
$superadminRole = Role::create(['name' => 'Superadmin']);
$superadminRole->givePermissionTo(Permission::all());

// Assign to user
$user->assignRole('Superadmin');
```

## Support

Untuk pertanyaan atau masalah terkait role Superadmin, silakan hubungi tim development atau buat issue di repository.

---

**Note**: Role Superadmin memiliki akses penuh ke sistem. Gunakan dengan bijak dan selalu backup data sebelum melakukan perubahan besar.
