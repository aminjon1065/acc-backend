# CK Accounting Backend
# Terms of Reference (ToR)

Version: 1.0  
Document Type: Backend Technical Specification  
Project: CK Accounting  
System Type: SaaS Accounting & Inventory Platform  

---

# 1. Project Overview

CK Accounting is a SaaS platform designed for small businesses to manage:

- product inventory
- sales operations
- purchases
- expenses
- debts
- financial reports

The platform supports multiple shops where each shop operates independently.

A global system administrator manages the entire platform.

---

# 2. System Architecture

The system follows a client-server architecture.

Mobile Client:
React Native application

Backend API:
Laravel REST API

Database:
PostgreSQL

Cache / Queue:
Redis

Infrastructure:

Mobile App
↓
Nginx
↓
Laravel API
↓
PostgreSQL
↓
Redis

---

# 3. System Type

The system is a **multi-tenant SaaS platform**.

Each tenant represents a shop.

All business data must be linked to:

shop_id

Example:

products.shop_id  
sales.shop_id  
expenses.shop_id  

Super Admin can access all tenants.

Shop users can access only their own shop data.

---

# 4. Technology Stack

Backend Framework

Laravel 11+

Language

PHP 8.2+

Database

PostgreSQL

Authentication

Laravel Sanctum

Caching

Redis

Queue

Laravel Queue

Deployment

Docker

Web Server

Nginx

---

# 5. User Roles

## 5.1 Super Admin

Global administrator of the system.

Permissions:

- manage shops
- view all system data
- suspend shops
- view global reports
- manage system settings

---

## 5.2 Shop Owner

Owner of a shop.

Permissions:

- manage products
- manage purchases
- manage sales
- manage expenses
- manage debts
- manage shop users
- view reports

---

## 5.3 Seller

Shop employee.

Permissions:

- create sales
- view products and inventory
- create/update products in own shop
- create/update expenses and debts in own shop
- create purchases in own shop
- view reports
- view own shop profile

Restrictions:

- cannot manage users
- cannot change settings
- cannot create/update/delete shops

---

# 6. Core System Modules

The backend consists of the following modules.

Authentication  
Users  
Shops  
Products  
Purchases  
Sales  
Expenses  
Debts  
Reports  
Currencies  
Settings  

Each module follows architecture:

Controller  
Service  
Repository  
Request validation  
API Resource

Note:

Service + Repository is mandatory for transaction-heavy modules (sales, purchases, debts, products, expenses) and is used progressively for all modules.

---

# 7. Authentication Module

Responsibilities:

- user login
- token generation
- logout
- token refresh

Technology:

Laravel Sanctum

Endpoints:

POST /auth/login  
POST /auth/logout  
POST /auth/refresh

Security:

- password hashing with bcrypt
- token expiration
- secure HTTP headers

---

# 8. Shops Module

Responsible for managing tenant shops.

Functions:

- create shop
- update shop
- suspend shop
- list shops
- view shop details

Access:

Super Admin: full CRUD  
Owner/Seller: view own shop only

Fields:

id  
name  
owner_name  
phone  
email  
address  
status  
created_at  

---

# 9. Users Module

Responsible for managing users within shops.

Functions:

- create user
- update user
- delete user
- assign role
- list users

Fields:

id  
shop_id  
name  
email  
password  
role  
created_at  

Roles:

super_admin  
owner  
seller  

---

# 10. Products Module

Responsible for product inventory.

Functions:

- create product
- update product
- delete product
- list products
- search products
- track stock

Fields:

id  
shop_id  
name  
code  
unit  
cost_price  
sale_price  
stock_quantity  
low_stock_alert  
created_at  

Units:

piece  
kg  
liter  
meter  

---

# 11. Purchases Module

Responsible for tracking product purchases.

Functions:

- create purchase invoice
- add purchased items
- update stock levels

Purchase fields:

id  
shop_id  
user_id  
supplier_name  
total_amount  
created_at  

Purchase item fields:

product_id  
quantity  
price  
total  

---

# 12. Sales Module

Responsible for sales transactions.

Functions:

- create sale
- add sale items
- calculate totals
- calculate customer debt

Fields:

id  
shop_id  
user_id  
discount  
paid  
debt  
total  
payment_type  
created_at  

Payment types:

cash  
card  
transfer  

---

# 13. Expenses Module

Responsible for recording business expenses.

Functions:

- create expense
- update expense
- delete expense
- list expenses

Fields:

id  
shop_id  
name  
quantity  
price  
total  
note  
created_at  

---

# 14. Debts Module

Responsible for tracking debts.

Functions:

- create debt
- record transactions
- track balance

Debt fields:

id  
shop_id  
person_name  
balance  
created_at  

Transaction types:

give  
take  
repay  

---

# 15. Reports Module

Provides financial analytics.

Reports include:

Sales Report  
Expense Report  
Profit Report  
Stock Report  

Profit formula:

profit =
sales_total
-
cost_of_goods_sold
-
expenses_total

---

# 16. Currencies Module

Supports multiple currencies.

Currencies:

TJS  
USD  
RUB  

Functions:

- view exchange rates
- update exchange rate

---

# 17. Settings Module

Shop configuration settings.

Fields:

default_currency  
tax_percent  

Functions:

- view settings
- update settings

---

# 18. Data Isolation Rules

All business entities must contain:

shop_id

All queries must enforce tenant isolation.

Example:

SELECT * FROM products WHERE shop_id = current_user.shop_id

Super Admin bypasses tenant filtering.

---

# 19. Performance Requirements

The system must support:

100 shops  
500 products per shop  
100 sales per day per shop  

Performance rules:

- use pagination
- use database indexes
- use eager loading
- use Redis caching

---

# 20. Security Requirements

All endpoints require authentication except login.

Security practices:

HTTPS only  
token-based authentication  
role-based authorization  
input validation  
secure HTTP headers  
token expiration policy

---

# 21. Error Handling

API errors must follow the format:

{
  "success": false,
  "message": "Error message"
}

---

# 22. Logging

System must log:

authentication events  
financial operations  
errors  

Logs stored using Laravel logging system.

---

# 23. Backup Strategy

Database must be backed up daily.

Backup retention:

30 days

---

# 24. Deployment

Recommended deployment architecture:

Docker containers

Services:

Nginx  
Laravel API  
Redis  
PostgreSQL  

---

# 25. Future Enhancements

Planned system improvements:

barcode scanning  
receipt printing  
push notifications  
web admin panel  
analytics dashboard  

---

# 26. Acceptance Criteria

The backend system is considered complete when:

- all modules are implemented
- all endpoints pass testing
- authentication and authorization work correctly
- tenant data isolation is enforced
- reports return accurate calculations
- system passes load tests
