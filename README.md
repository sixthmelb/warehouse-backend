# 📦 Warehouse Management API

Sistem backend untuk **manajemen gudang** dengan dukungan **multi-gudang**, pencatatan transaksi barang masuk/keluar, audit trail pergerakan stok, dan integrasi vendor.  
Dibangun menggunakan **Laravel** sebagai backend RESTful API.

---

## 🚀 Fitur Utama

- **Manajemen Multi-Gudang**: Satu user bisa mengelola lebih dari satu gudang.
- **Master Data Barang**: Kategori, item, vendor.
- **Transaksi Barang IN/OUT**: Stok otomatis bertambah/berkurang.
- **Audit Trail (Stock Movements)**: Setiap perubahan stok tercatat detail.
- **Validasi Stok**: Tidak bisa transaksi OUT jika stok kurang.
- **Laporan Stok & Pergerakan Barang**.

---

## 🗂 Desain Database

### **Entitas**
1. **Users** → Admin/staff gudang.
2. **Warehouses** → Data gudang.
3. **Vendors** → Supplier barang.
4. **Categories** → Kategori item.
5. **Items** → Master data barang.
6. **Transactions** → Header transaksi (IN/OUT).
7. **Transaction_Details** → Detail barang per transaksi.
8. **Stock_Movements** → Log pergerakan stok (audit trail).

### **Relasi**
- **User** `belongsToMany` **Warehouses**
- **Warehouse** `hasMany` **Transactions**
- **Vendor** `hasMany` **Transactions** *(khusus IN)*
- **Category** `hasMany` **Items**
- **Item** `hasMany` **Transaction_Details**
- **Transaction** `hasMany` **Transaction_Details**
- **Item** `hasMany` **Stock_Movements**

---

## 🔗 API Endpoints

### **Authentication**
| Method | Endpoint        | Deskripsi |
|--------|-----------------|-----------|
| POST   | `/api/login`    | Login user & dapatkan token |
| POST   | `/api/logout`   | Logout user |
| GET    | `/api/user`     | Profil user login |

### **Warehouses**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET    | `/api/warehouses` | List semua gudang |
| POST   | `/api/warehouses` | Tambah gudang baru |
| PUT    | `/api/warehouses/{id}` | Update gudang |
| DELETE | `/api/warehouses/{id}` | Hapus gudang |

### **Vendors**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET    | `/api/vendors` | List semua vendor |
| POST   | `/api/vendors` | Tambah vendor |
| PUT    | `/api/vendors/{id}` | Update vendor |
| DELETE | `/api/vendors/{id}` | Hapus vendor |

### **Items**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET    | `/api/items` | List semua item |
| POST   | `/api/items` | Tambah item |
| PUT    | `/api/items/{id}` | Update item |
| DELETE | `/api/items/{id}` | Hapus item |
| GET    | `/api/items/{id}/stock` | Cek stok item |

### **Transactions**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET    | `/api/transactions` | List semua transaksi |
| POST   | `/api/transactions` | Buat transaksi baru (IN/OUT) |
| GET    | `/api/transactions/{id}` | Detail transaksi |
| PUT    | `/api/transactions/{id}` | Update transaksi |
| DELETE | `/api/transactions/{id}` | Hapus transaksi |

### **Reports**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET    | `/api/reports/stock` | Laporan stok semua item |
| GET    | `/api/reports/movements` | Laporan pergerakan stok |

---

## 🧠 Business Logic

1. **Transaksi IN**
   - Menambah stok item pada gudang terkait.
   - Mencatat detail ke tabel `stock_movements`.

2. **Transaksi OUT**
   - Mengurangi stok item.
   - **Validasi:** Tidak bisa jika stok < jumlah permintaan.
   - Mencatat detail ke tabel `stock_movements`.

3. **Audit Trail**
   - Semua perubahan stok tercatat di `stock_movements` (user, tanggal, qty, jenis transaksi).

4. **Multi-Warehouse Support**
   - Stok item dipisahkan per gudang.
   - User bisa terhubung ke lebih dari satu gudang.

---

## 🛠 Teknologi

- **Backend:** Laravel (RESTful API)
- **Database:** MySQL/MariaDB
- **Autentikasi:** Laravel Sanctum / Passport
- **Dokumentasi API:** Laravel API Resource / Swagger (opsional)
- **Testing:** PHPUnit / Pest

---

## 📦 Instalasi

```bash
# Clone repo
git clone https://github.com/username/warehouse-api.git
cd warehouse-api

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Migrasi database
php artisan migrate --seed

# Jalankan server
php artisan serve
