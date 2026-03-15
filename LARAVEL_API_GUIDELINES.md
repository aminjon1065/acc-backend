# API Guidelines — CK Accounting

All APIs follow REST standards.

Base URL

/api/v1

---

# Authentication

Auth system:

Laravel Sanctum

Header:

Authorization: Bearer token

---

# Response Format

All API responses follow this format.

{
  "success": true,
  "data": {},
  "message": ""
}

---

# Error Format

{
  "success": false,
  "message": "Validation error",
  "errors": {}
}

---

# Pagination

List endpoints support pagination.

GET /products?page=1&limit=20

Response:

{
  "data": [],
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 20
  }
}

---

# Resource Naming

Plural resources.

products
sales
expenses
purchases

---

# HTTP Methods

GET

retrieve data

POST

create resource

PATCH

update resource

DELETE

remove resource

---

# Example Endpoint

Create Product

POST /products

Request

{
  "name": "Product",
  "cost_price": 5,
  "sale_price": 10,
  "stock_quantity": 100
}

---

# Role Access

Super Admin

- manage shops
- view all data
- manage owners and sellers
- manage currencies and settings
- access all reports

Owner

- manage products
- manage sales
- manage expenses
- manage purchases
- manage debts
- manage only sellers in own shop
- view own shop reports and settings

Seller

- create sales
- view products
- view stock
- view own shop
- view and update own profile
