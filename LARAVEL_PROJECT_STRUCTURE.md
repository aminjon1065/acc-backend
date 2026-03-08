# Laravel Project Structure — CK Accounting

This document describes the backend folder architecture.

The project follows a **Service + Repository pattern** for maintainability.

---

# Root Structure

app
bootstrap
config
database
routes
storage
tests

---

# App Folder

app/

Models
Http
Services
Repositories
DTO
Policies
Jobs

---

# Models

app/Models

Each model represents a database entity.

Examples:

User
Shop
Product
Sale
SaleItem
Purchase
PurchaseItem
Expense
Debt

---

# Controllers

app/Http/Controllers/API

Controllers handle HTTP requests only.

Business logic must NOT be inside controllers.

Example controllers:

AuthController
ProductController
SaleController
PurchaseController
ExpenseController
DebtController
ReportController

---

# Requests (Validation)

app/Http/Requests

Used for request validation.

Example:

CreateProductRequest
UpdateProductRequest
CreateSaleRequest

---

# Resources

app/Http/Resources

Used for API responses.

Example:

ProductResource
SaleResource
ExpenseResource

---

# Services

app/Services

Contains business logic.

Example:

ProductService
SaleService
PurchaseService
ExpenseService
DebtService
ReportService

Responsibilities:

- orchestrate business operations
- call repositories
- enforce business rules

---

# Repositories

app/Repositories

Handles database access.

Example:

ProductRepository
SaleRepository
PurchaseRepository
ExpenseRepository

Repositories interact with Eloquent models.

---

# DTO

app/DTO

Data Transfer Objects.

Used to pass structured data between layers.

Example:

CreateProductDTO
CreateSaleDTO

---

# Policies

app/Policies

Authorization rules.

Example:

ProductPolicy
SalePolicy
ExpensePolicy

---

# Jobs

app/Jobs

Background jobs.

Examples:

GenerateReportJob
SendNotificationJob

Uses Laravel Queue.

---

# Routes

routes/api.php

All API routes are defined here.

Example:

Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('products', ProductController::class);
    Route::apiResource('sales', SaleController::class);
    Route::apiResource('purchases', PurchaseController::class);
    Route::apiResource('expenses', ExpenseController::class);

});

---

# Database

database/

migrations
seeders
factories

---

# Migrations

Each table has its own migration.

Examples:

create_shops_table
create_products_table
create_sales_table
create_expenses_table

---

# Tests

tests/

Feature
Unit

Example:

ProductTest
SaleTest
ExpenseTest

---

# Coding Rules

Controllers must be thin.

Business logic belongs in Services.

Database queries belong in Repositories.

Validation belongs in Requests.

Responses must use API Resources.

---

# Multi-Tenant Rule

Every business model must include:

shop_id

Example query:

Product::where('shop_id', auth()->user()->shop_id)

Super Admin bypasses this restriction.
