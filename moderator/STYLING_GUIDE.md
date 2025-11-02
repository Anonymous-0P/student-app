# Moderator Panel - Consistent Styling Guide

## Overview
All moderator panel pages now use a consistent, minimal, professional design with a shared stylesheet.

## Shared Stylesheet
**Location**: `/moderator/css/moderator-style.css`

This file contains all common styles for:
- Page headers
- Cards and containers
- Tables
- Buttons
- Badges
- Forms
- Responsive design

## How to Apply to a Moderator Page

### 1. Update PHP Header Section
```php
<?php
require_once('../config/config.php');
require_once('../includes/functions.php');

// Check if user is logged in and is a moderator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'moderator'){
    header("Location: ../auth/login.php");
    exit();
}

include('../includes/header.php');
?>
<link rel="stylesheet" href="css/moderator-style.css">

<div class="moderator-content">
```

### 2. Close the Content Wrapper
At the end of the page, before footer:
```php
</div><!-- Close moderator-content -->

<?php include('../includes/footer.php'); ?>
```

### 3. Remove Old Inline Styles
- Remove any gradient backgrounds
- Remove custom color variables (use CSS variables from stylesheet)
- Remove duplicate button/table styles

## CSS Variables Available

```css
--primary-color: #2563eb
--primary-dark: #1e40af
--success-color: #10b981
--warning-color: #f59e0b
--danger-color: #ef4444
--text-dark: #1f2937
--text-muted: #6b7280
--border-color: #e5e7eb
--bg-light: #f9fafb
```

## Common Components

### Page Header
```html
<div class="page-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Page Title</h1>
                <p>Description text</p>
            </div>
            <div class="d-flex gap-3">
                <a href="..." class="btn btn-outline-primary">Action</a>
            </div>
        </div>
    </div>
</div>
```

### Dashboard Card
```html
<div class="dashboard-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Card Title</h5>
        <a href="..." class="btn btn-outline-primary btn-sm">Action</a>
    </div>
    <!-- Card content -->
</div>
```

### Stat Boxes
```html
<div class="card-stats">
    <div class="stat-box">
        <h4>123</h4>
        <small>Label</small>
    </div>
    <!-- More stat boxes -->
</div>
```

### Tables
```html
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Column 1</th>
                <th>Column 2</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Data</td>
                <td>Data</td>
            </tr>
        </tbody>
    </table>
</div>
```

### Badges
```html
<span class="badge bg-success">Success</span>
<span class="badge bg-warning">Warning</span>
<span class="badge bg-danger">Danger</span>
<span class="badge bg-info">Info</span>
<span class="badge bg-primary">Primary</span>
```

### Buttons
```html
<button class="btn btn-primary">Primary</button>
<a href="..." class="btn btn-outline-primary">Outline</a>
<button class="btn btn-sm btn-primary">Small</button>
```

## Pages Already Updated
✅ dashboard.php
✅ marks_access.php
✅ evaluator_performance.php
✅ view_evaluation.php

## Pages to Update (if needed)
- assign_evaluator.php
- check_marking_consistency.php
- marks_overview.php
- reports.php
- subject_detail.php
- submissions.php
- Any other moderator pages

## Design Principles
1. **Minimal**: Clean white backgrounds, subtle borders
2. **Professional**: System fonts, consistent spacing
3. **Readable**: Good contrast, proper font sizes
4. **Responsive**: Mobile-friendly design
5. **Fast**: No heavy animations or gradients
6. **Accessible**: Semantic HTML, proper ARIA labels
