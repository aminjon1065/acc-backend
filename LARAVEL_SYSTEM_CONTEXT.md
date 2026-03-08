# CK Accounting — System Context (Laravel Backend)

## Overview

CK Accounting is a SaaS accounting and inventory management platform designed for small businesses such as shops and service providers.

The system supports:

- inventory management
- sales tracking
- purchase tracking
- expense tracking
- debt management
- financial reporting

The backend is implemented using **Laravel API architecture**.

Mobile client:

React Native / Expo

Backend:

Laravel 11/12

Database:

PostgreSQL

Cache:

Redis

Deployment:

Docker + Nginx

---

# SaaS Architecture

The system is **multi-tenant**.

Each shop represents an independent tenant.

All business entities must include:

shop_id

Example tables:

products.shop_id
sales.shop_id
expenses.shop_id
debts.shop_id

Super Admin can access all shops.

Shop users can access only their own shop data.

---

# User Roles

## Super Admin

Global administrator of the platform.

Permissions:

- manage shops
- view system analytics
- suspend shops
- access all data

---

## Shop Owner

Owner of a shop.

Permissions:

- manage products
- manage purchases
- manage sales
- manage expenses
- manage debts
- manage shop users

---

## Seller

Shop employee.

Permissions:

- create sales
- view products
- view stock

Restrictions:

- cannot manage users
- cannot access global reports

---

# Core Modules

The system consists of the following modules:

Auth
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

Each module contains:

- Controller
- Service
- Repository
- Request validation
- Resource responses

---

# Financial Logic

Profit calculation:

profit =
total_sales
- cost_of_goods
- expenses

Cost of goods is derived from purchase prices.

---

# Security

Authentication:

Laravel Sanctum

Authorization:

Policies + Gates

---

# Performance

Use:

- Redis cache
- pagination
- database indexes
- eager loading

---

# Future Features

Planned features:

- barcode scanning
- receipt printing
- cloud backups
- push notifications
- web dashboard
