# Demo School Acceptance-Test Data Template

## Important: do not build the database tables manually
The ERP migrations create the database schema. Your job is to provide the **existing Demo School data** in an Excel workbook or a sanitized SQL export. Until then, the isolated `tagore_release_test` database is the test target. It is separate from the production database. Synthetic test records may be created only inside that isolated database; production records and accounts must remain untouched.

## Best delivery format
Preferred: a sanitized `.sql.gz` export of the existing Demo School database, excluding secrets and unrelated schools where possible.

Alternative: one `.xlsx` workbook with one worksheet per section below. Keep IDs/keys stable across sheets; use the same `student_code`, `staff_code`, `parent_code`, and `academic_year_code` on related sheets. Use fictional names and contact details if the data can be anonymized.

## Workbook worksheets and fields

### 1. School
Required columns: `school_code`, `school_name`, `school_type`, `board`, `medium`, `academic_year_start`, `academic_year_end`, `timezone`, `status`.
Optional: `address_line`, `village_city`, `district`, `state`, `postal_code`, `office_phone_redacted`, `office_email_redacted`.

### 2. AcademicYears
Required: `academic_year_code`, `school_code`, `year_name`, `start_date`, `end_date`, `is_active`.

### 3. ClassesSections
Required: `class_code`, `class_name`, `section_code`, `section_name`, `academic_year_code`, `medium`, `class_teacher_staff_code` (may be blank).

### 4. Students
Required: `student_code` (stable unique key, e.g. existing SR/Admission number), `student_name`, `class_code`, `section_code`, `academic_year_code`, `admission_date`, `status`.
Optional: `date_of_birth`, `gender`, `roll_number`, `medium`, `house`, `transport_route_code`. Avoid government ID numbers.

### 5. ParentsGuardians
Required: `parent_code`, `parent_name`, `relationship`, `student_code`, `is_primary_guardian`.
Optional: `phone_redacted_or_test`, `email_redacted_or_test`, `address_redacted`. Do not include Aadhaar, banking details, or real passwords.

### 6. Staff
Required: `staff_code`, `staff_name`, `department`, `designation`, `employment_status`.
Optional: `work_email_test`, `phone_redacted_or_test`, `joining_date`, `class_teacher_for`. Do not include salary bank details or government IDs.

### 7. ExistingTestAccounts (only if you later decide to map existing accounts)
Columns: `account_code`, `login_email`, `intended_role`, `linked_staff_code_or_student_code`, `can_use_for_testing`.
Do not provide passwords in this sheet or create new accounts in production. You have said there are currently no test accounts, so this sheet can be omitted for now. A separate set of clearly labelled synthetic accounts can be provisioned in the disposable test database only.

### 8. FeeStructures
Required: `fee_structure_code`, `academic_year_code`, `class_code`, `fee_head`, `amount`, `frequency`, `effective_from`, `effective_to`, `status`.
Use numeric amounts in INR; do not include payment gateway secrets.

### 9. StudentFeeLedger (representative test rows)
Required: `student_code`, `academic_year_code`, `fee_head`, `due_amount`, `paid_amount`, `balance_amount`, `due_date`, `ledger_status`.
For privacy and reconciliation, use sanitized examples or a small representative subset. Do not send card numbers, UPI PINs, bank account numbers, or gateway tokens.

### 10. PaymentsReceipts (sanitized)
Required: `receipt_ref_test`, `student_code`, `payment_date`, `amount`, `payment_method_category`, `allocation_fee_head`, `status`.
Replace actual transaction IDs with synthetic references; exclude bank/gateway credentials.

### 11. Attendance
Required: `student_code_or_staff_code`, `record_date`, `attendance_status`, `class_code_or_department`, `academic_year_code`.

### 12. ExamsResults
Required: `exam_code`, `exam_name`, `academic_year_code`, `class_code`, `student_code`, `subject`, `max_marks`, `marks_obtained`, `result_status`.

### 13. AdmissionsCRM (sanitized)
Required: `lead_ref`, `lead_date`, `student_prospect_name_or_alias`, `class_applied_for`, `source_channel`, `lead_status`, `assigned_staff_code`, `follow_up_date`.
Optional: `campaign_code`, `notes_redacted`. Remove personal contact details unless genuinely needed for the workflow test.

### 14. StaffTasks (representative)
Required: `task_ref`, `title`, `assigned_staff_code`, `created_by_staff_code`, `priority`, `status`, `due_date`, `department`.

### 15. TransportRoutes (if testing transport)
Required: `transport_route_code`, `route_name`, `vehicle_alias`, `capacity`, `status`.
Optional: `stop_name`, `stop_order`, `student_code`. Do not include precise home addresses.

### 16. Olympiad (synthetic/optional)
Required: `olympiad_code`, `title`, `target_classes`, `starts_at`, `ends_at`, `max_marks`, `status`.
Questions should be sample/licensed material only; mark imported questions as pending review until an authorized reviewer approves them.

## Minimum viable dataset if preparing manually
If the full workbook is too much, start with these sheets: **School, AcademicYears, ClassesSections, Students, ParentsGuardians, Staff, FeeStructures, StudentFeeLedger, PaymentsReceipts, Attendance**. Aim for 1 school, 1 active academic year, 2 classes, 2 sections, 5–10 students, 2 guardians, 3 staff (principal/teacher/accounts), 2 fee heads, and 5–10 sanitized fee/attendance transactions. We can use this to test the data relationships, then expand coverage.

## Hikvision CCTV details to provide later
Provide the exact DVR/NVR model, firmware version, camera models, whether cameras are IP or analog connected through DVR, and whether ONVIF is enabled/supported. **Do not send passwords.** We will use ONVIF for supported discovery/device metadata, RTSP for live streams, and Hikvision ISAPI only for supported event/configuration features. Keep the recorder on the private LAN/VPN; do not port-forward camera/DVR services to the public internet.

## How we will replace the test data later
1. Keep the current disposable test database separate from production.
2. Back up the disposable test database before any import.
3. Import your sanitized Demo School data into a fresh isolated database, not over production.
4. Validate row counts, foreign-key relationships, fees/receipts reconciliation, and role links.
5. Run migrations and acceptance tests against that isolated copy.
6. Do not switch production to the imported database until a separately approved, backed-up deployment plan is validated.
