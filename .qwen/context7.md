# 🤖 Context7 — AI Assistant Coding Standards & Best Practices

> **Purpose:** Single Source of Truth untuk coding standards, best practices, dan guidelines yang digunakan oleh AI assistant dalam development inTime.  
> **Last Updated:** 19 Februari 2026  
> **Priority:** HIGHEST — Semua kode yang dihasilkan AI HARUS mengikuti standards ini.

---

## 📋 Table of Contents

1. [PSR-12 Coding Standards](#1-psr-12-coding-standards)
2. [Laravel Best Practices](#2-laravel-best-practices)
3. [Filament Conventions](#3-filament-conventions)
4. [Code Review Checklist](#4-code-review-checklist)
5. [Documentation Standards](#5-documentation-standards)
6. [Git & Version Control](#6-git--version-control)
7. [Security Best Practices](#7-security-best-practices)
8. [Performance Optimization](#8-performance-optimization)

---

## 1. PSR-12 Coding Standards

### 1.1 File Structure

```php
<?php

namespace App\Filament\Resources\Members;

// 1. App imports (alphabetical)
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\Member;

// 2. Vendor imports (alphabetical)
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

// 3. Facades (alphabetical)
use Illuminate\Support\Facades\Storage;

class MemberResource extends Resource
{
    // ...
}
```

**Rules:**
- ✅ Semua `use` statements di awal file setelah `namespace`
- ✅ Urutan: App → Vendor → Facades
- ✅ Alphabetical dalam setiap grup
- ✅ Tidak ada fully qualified namespace (`\`) di tengah kode
- ✅ Satu blank line antara grup import

### 1.2 Import Examples

✅ **BENAR:**
```php
use App\Models\Member;
use App\Models\Group;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
```

❌ **SALAH:**
```php
// FQN di tengah kode
\Filament\Actions\ActionGroup::make([...])

// Import di tengah file
use Filament\Tables\Table; // ← Salah posisi

// Missing use statement
ActionGroup::make([...]) // ← Error: class not found
```

### 1.3 Type Hints & Return Types

```php
// ✅ BENAR
use Illuminate\Database\Eloquent\Builder;

public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery();
}

// ❌ SALAH
public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
{
    return parent::getEloquentQuery();
}
```

### 1.4 Class Structure

```php
class MemberResource extends Resource
{
    // 1. Static properties
    protected static ?string $model = Member::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    
    // 2. Static methods (public → protected → private)
    public static function form(Schema $schema): Schema
    {
        // ...
    }
    
    public static function table(Table $table): Table
    {
        // ...
    }
    
    protected static function getEloquentQuery(): Builder
    {
        // ...
    }
    
    // 3. Instance methods
    // 4. Magic methods (__construct, __destruct)
}
```

---

## 2. Laravel Best Practices

### 2.1 Eloquent ORM

```php
// ✅ BENAR - Gunakan Eloquent
$members = Member::where('status', true)
    ->with(['group', 'ageGroup'])
    ->orderBy('full_name')
    ->get();

// ❌ KURANG BAIK - Raw query
$members = DB::select('SELECT * FROM members WHERE status = ?', [true]);
```

### 2.2 Relationships

```php
// ✅ BENAR - Define relationships di model
class Member extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
    
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}

// Usage
$member->group->name;
$member->attendances->count();
```

### 2.3 Service Layer

```php
// ✅ BENAR - Extract logic ke service class
class MemberService
{
    public function importMembers(array $data): void
    {
        // Business logic here
    }
}

// Controller/Resource
public function store(MemberService $service, Request $request)
{
    $service->importMembers($request->all());
}
```

### 2.4 Validation

```php
// ✅ BENAR - Form Request atau inline validation
$data = $request->validate([
    'full_name' => 'required|string|max:255',
    'group_name' => 'required|string|exists:groups,name',
    'birth_date' => 'nullable|date|before:today',
    'gender' => 'nullable|in:l,p',
]);

// ❌ SALAH - No validation
$data = $request->all();
```

### 2.5 Error Handling

```php
// ✅ BENAR - Try-catch dengan proper handling
try {
    Excel::import(new MemberImporter(), $filePath);
    
    Notification::make()
        ->title('Import Berhasil')
        ->success()
        ->send();
} catch (\Exception $e) {
    Log::error('Import failed', ['error' => $e->getMessage()]);
    
    Notification::make()
        ->title('Import Gagal')
        ->body($e->getMessage())
        ->danger()
        ->send();
}
```

---

## 3. Filament Conventions

### 3.1 Resource Structure

```
app/Filament/Resources/
├── Members/
│   ├── MemberResource.php          ← Main resource
│   ├── Schemas/
│   │   ├── MemberForm.php          ← Form schema
│   │   └── MemberInfolist.php      ← Infolist schema
│   ├── Tables/
│   │   └── MembersTable.php        ← Table schema
│   └── Pages/
│       ├── CreateMember.php
│       ├── EditMember.php
│       ├── ListMembers.php
│       └── ViewMember.php
```

### 3.2 Schema Extraction

```php
// ✅ BENAR - Extract schema ke separate class
// MemberResource.php
public static function form(Schema $schema): Schema
{
    return MemberForm::configure($schema);
}

// Schemas/MemberForm.php
class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')->required(),
            // ...
        ]);
    }
}
```

### 3.3 Actions & Bulk Actions

```php
// ✅ BENAR - Import di awal
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

// Usage
->actions([
    ActionGroup::make([
        ViewAction::make(),
        EditAction::make(),
        DeleteAction::make(),
    ])
])

// ❌ SALAH - FQN
->actions([
    \Filament\Actions\ActionGroup::make([...])
])
```

### 3.4 Table Columns

```php
// ✅ BENAR - Reusable column definitions
TextColumn::make('full_name')
    ->label('Nama Lengkap')
    ->searchable()
    ->sortable()
    ->toggleable(),

// Badge dengan color
TextColumn::make('status')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'active' => 'success',
        'inactive' => 'danger',
        default => 'gray',
    }),
```

### 3.5 Filters

```php
// ✅ BENAR - Proper filter definition
SelectFilter::make('group_id')
    ->label('Kelompok')
    ->relationship('group', 'name')
    ->multiple()
    ->searchable()
    ->preload(),

TernaryFilter::make('status')
    ->label('Status Aktif')
    ->placeholder('Semua Status')
    ->trueLabel('Aktif Saja')
    ->falseLabel('Non-Aktif Saja'),
```

---

## 4. Code Review Checklist

### 4.1 Pre-Commit Checklist

- [ ] PSR-12 compliance (import statements, naming)
- [ ] No FQN in code (all imported at top)
- [ ] Type hints untuk function parameters
- [ ] Return type declarations
- [ ] No hard-coded strings (use translation)
- [ ] Proper error handling
- [ ] Logging untuk critical operations
- [ ] No sensitive data (passwords, API keys)

### 4.2 Performance Checklist

- [ ] Eager loading untuk N+1 queries
- [ ] Index pada kolom yang sering di-query
- [ ] Pagination untuk large datasets
- [ ] Cache untuk expensive operations
- [ ] Lazy loading untuk heavy components
- [ ] Query optimization (select only needed columns)

### 4.3 Security Checklist

- [ ] Input validation
- [ ] Output escaping
- [ ] CSRF protection
- [ ] SQL injection prevention (use Eloquent/Query Builder)
- [ ] XSS prevention
- [ ] Authorization checks (`can()`, policies)
- [ ] No sensitive data in logs

### 4.4 Documentation Checklist

- [ ] PHPDoc untuk complex methods
- [ ] Inline comments untuk "why", bukan "what"
- [ ] Update SSOT jika ada perubahan signifikan
- [ ] Update changelog
- [ ] Update todolist jika ada task baru

---

## 5. Documentation Standards

### 5.1 File Documentation

```php
<?php

/**
 * Member Importer Class
 * 
 * Handles bulk import of members from Excel files.
 * Implements Maatwebsite Excel concerns for validation and batch processing.
 * 
 * @package App\Filament\Imports
 * @author inTime Team
 * @since 1.6.0
 */
class MemberImporter implements ToModel, WithHeadingRow, WithValidation
{
    // ...
}
```

### 5.2 Method Documentation

```php
/**
 * Find group by name (case-insensitive)
 * 
 * Searches for a group by its name, with fallback to case-insensitive search.
 * Results are cached for performance optimization.
 * 
 * @param string $groupName Group name to search
 * @return int|null Group ID if found, null otherwise
 * 
 * @throws \Exception If database query fails
 */
protected function findGroup(string $groupName): ?int
{
    // ...
}
```

### 5.3 SSOT Updates

**When to update SSOT:**
- ✅ New feature implemented
- ✅ Database schema changes
- ✅ Role/permission changes
- ✅ Architecture decisions
- ✅ Breaking changes

**When NOT to update SSOT:**
- ❌ Bug fixes (update changelog only)
- ❌ Minor refactoring
- ❌ Documentation typos

---

## 6. Git & Version Control

### 6.1 Commit Message Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting)
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(import): add Excel template download feature

- Create MemberTemplateExport class with 2 sheets
- Add download button in import modal
- Include documentation sheet with column descriptions

Closes #123

---

fix(members): resolve QR code generation issue on import

- Move QR generation from controller to Observer
- Ensure QR is generated after member save
- Add error handling for storage failures

Fixes #456
```

### 6.2 Branch Naming

```
feature/<description>     # New features
fix/<description>         # Bug fixes
hotfix/<description>      # Urgent production fixes
docs/<description>        # Documentation updates
refactor/<description>    # Code refactoring
```

**Examples:**
```
feature/import-template
fix/qr-code-generation
docs/ssot-update
refactor/psr-12-compliance
```

---

## 7. Security Best Practices

### 7.1 Input Validation

```php
// ✅ BENAR - Validate semua input
$data = $request->validate([
    'email' => 'required|email|unique:users,email',
    'password' => 'required|min:8|confirmed',
    'role' => 'required|in:admin,operator',
]);

// ❌ SALAH - No validation
$data = $request->all();
Member::create($data);
```

### 7.2 Authorization

```php
// ✅ BENAR - Check authorization
public function delete(Member $member)
{
    abort_unless($member->canBeManagedBy(auth()->user()), 403);
    
    $member->delete();
}

// Use policies
public function view(Member $member)
{
    $this->authorize('view', $member);
    
    return view('members.show', compact('member'));
}
```

### 7.3 Data Protection

```php
// ✅ BENAR - Hash passwords
$user = User::create([
    'password' => Hash::make($request->password),
]);

// ✅ BENAR - Use parameterized queries
$member = Member::where('email', $email)->first();

// ❌ SALAH - SQL injection risk
$member = DB::select("SELECT * FROM members WHERE email = '$email'");
```

### 7.4 File Upload Security

```php
// ✅ BENAR - Validate file uploads
FileUpload::make('file')
    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
    ->maxSize('10240') // 10MB
    ->disk('public')
    ->directory('imports')
    ->required();

// Validate in controller
$request->validate([
    'file' => 'required|file|mimes:xlsx,xls|max:10240',
]);
```

---

## 8. Performance Optimization

### 8.1 Eager Loading

```php
// ✅ BENAR - Eager load relationships
$members = Member::with(['group', 'ageGroup', 'attendances'])->get();

// ❌ SALAH - N+1 query problem
$members = Member::all();
foreach ($members as $member) {
    echo $member->group->name; // Query per iteration
}
```

### 8.2 Caching

```php
// ✅ BENAR - Cache expensive queries
$groups = Cache::remember('groups_cache', 3600, function () {
    return Group::all()->pluck('id', 'name')->toArray();
});

// Use cache tags for selective clearing
Cache::tags(['groups', 'members'])->flush();
```

### 8.3 Lazy Loading

```php
// ✅ BENAR - Lazy load heavy components
protected function getHeaderWidgets(): array
{
    return [
        AttendanceOverview::make(),
        AttendanceTrend::make(),
        GroupRanking::make(),
    ];
}

// Filament v3 lazy loading
public function getWidgets(): array
{
    return [
        Lazy::make(GroupRanking::class),
    ];
}
```

### 8.4 Query Optimization

```php
// ✅ BENAR - Select only needed columns
$members = Member::select('id', 'full_name', 'group_id')->get();

// Use chunk for large datasets
Member::chunk(200, function ($members) {
    foreach ($members as $member) {
        // Process
    }
});

// ❌ SALAH - Select all
$members = Member::all();
```

---

## 📚 Additional Resources

- **PSR-12:** https://www.php-fig.org/psr/psr-12/
- **Laravel Docs:** https://laravel.com/docs
- **Filament Docs:** https://filamentphp.com/docs
- **Spatie Permission:** https://spatie.be/docs/laravel-permission

---

## 🔄 Changelog

| Date | Changes |
|------|---------|
| 19 Feb 2026 | Initial creation — PSR-12, Laravel, Filament standards |
| | Added Context7 integration with SSOT |
| | Added Code Review Checklist |
| | Added Security & Performance guidelines |

---

> **⚠️ IMPORTANT FOR AI ASSISTANTS:**
> 
> 1. **ALWAYS** read this file before generating code
> 2. **ALWAYS** follow PSR-12 import rules
> 3. **ALWAYS** use type hints and return types
> 4. **ALWAYS** extract schemas to separate classes
> 5. **ALWAYS** update documentation if changing significant logic
> 
> **Priority Order:**
> ```
> .qwen/context7.md (HIGHEST — Coding Standards)
>     ↓
> docs/ssot.md (Project Truth)
>     ↓
> docs/todolist/phase-X-todolist.md (Active Tasks)
>     ↓
> Other docs (Reference)
> ```
