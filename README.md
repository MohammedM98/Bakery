# نظام إدارة المخابز (Bakery Management System)

منصة SaaS متعددة المخابز: مالك المنصة يضيف المخابز ويجدد اشتراكاتها الشهرية، ولكل مخبز لوحة تحكم خاصة لتسجيل المبيعات، وإدارة الزبائن وأرصدتهم المالية وأرصدة الطحين، وكشوف الحساب، وموظفي المخبز وصلاحياتهم. الواجهة عربية بالكامل من اليمين لليسار، ومصممة للجوال أولًا.

## الأدوار

| الدور | ما يفعله |
|---|---|
| مالك المنصة (`super_admin`) | يضيف المخابز مع حساب صاحب كل مخبز، يعدّلها، يجدد الاشتراك أو يوقفه، ويحذف المخبز |
| صاحب المخبز (`bakery_owner`) | يدير مخبزه وحده: المبيعات، الزبائن، الكشوف، البنوك، الموظفين، والأسعار |
| موظف المخبز (`bakery_staff`) | يعمل داخل مخبز واحد بالصلاحيات التي يحددها له صاحب المخبز بمربعات اختيار، ويمكن إيقاف حسابه |

## المزايا

- **البيع النقدي بالكيلو** بثلاث نقرات، مع مشترٍ عابر باسم وجوال اختياريين، وحالة "مدفوع / غير مدفوع" وتأكيد الدفع لاحقًا.
- **البيع مقابل الطحين:** الزبون يودِع طحينًا ويأخذ خبزًا على دفعات (كيلو بكيلو)، ورسم التبديل لكل كيلو يُحمَّل على رصيده المالي. لا يُرفض البيع إن نفد رصيده.
- **جدول حركات موحّد** لكل عملية برقم تسلسلي خاص بالمخبز واسم من سجّلها. الحركات لا تُعدَّل ولا تُحذف؛ التصحيح بقيد تسوية بسبب إلزامي.
- **كشفا حساب لكل زبون** (المالي والطحين) لفترة يختارها المستخدم، مع "رصيد سابق" والرصيد الجاري والختامي، والبحث داخل الكشف، و**تحميلهما PDF و Excel**.
- **الدفعات** نقدًا أو تحويلًا ("من ← إلى" من قائمة بنوك ومحافظ المخبز، ورقم حوالة اختياري)، على الحساب أو عن بيع محدد.
- **سجل مبيعات المخبز** باليوم أو الأسبوع أو الشهر أو السنة: الخبز المباع، الدخل المالي نقدًا وتحويلًا، الطحين المصروف والمستلم، وجدول الحركات مع فلاتر.
- **لوحة رئيسية** بخبز اليوم ورسم آخر 7 أيام والمبيعات غير المدفوعة.
- **عزل كامل بين المخابز**، وإيقاف تلقائي للوحة عند انتهاء الاشتراك.
- وضعان فاتح وداكن، وصفحات أخطاء عربية (403، 404، 419، 500، 503).

## البنية التقنية

- **الخلفية:** Laravel 13، PHP 8.3+. MySQL أو PostgreSQL في الإنتاج، و SQLite محليًا وفي الاختبارات.
- **الواجهة:** React 19 + TypeScript عبر Inertia.js، مع Tailwind CSS 4 و Vite ومكوّنات shadcn/ui (RTL)، وخط IBM Plex Sans Arabic.
- **التصدير:** mPDF (عربي RTL بخط IBM Plex Sans Arabic المضمَّن في `resources/fonts`) و PhpSpreadsheet.
- **الأدوار والصلاحيات:** Middleware باسم `role`، ثم `subscription.active` للوحة المخبز، ثم بوابة `can:<permission>` لكل مسار (`app/Providers/AppServiceProvider.php`، وقائمة الصلاحيات في `User::PERMISSIONS`).
- **التعليمات الملزمة للتطوير** في `CLAUDE.md`.

```
app/Http/Controllers/Admin/   متحكمات مالك المنصة
app/Http/Controllers/Owner/   متحكمات صاحب المخبز والموظف
app/Http/Requests/            التحقق من كل إدخال (برسائل عربية)
app/Actions/                  العمليات المركّبة داخل transaction
resources/js/Pages/           صفحات React
resources/js/components/      المكوّنات المشتركة و components/ui لمكوّنات shadcn
```

## المتطلبات

- PHP 8.3 أو أحدث مع الإضافات: `pdo_sqlite` (محليًا) أو `pdo_mysql` / `pdo_pgsql`، و `mbstring` و `gd` و `zip` و `xml` و `dom`.
- Composer 2
- Node.js 22 و npm

## الإعداد المحلي

### Linux و macOS

```bash
composer install
npm install --ignore-scripts

cp .env.example .env
# في .env: اجعل DB_CONNECTION=sqlite وعلّق سطر DB_DATABASE
touch database/database.sqlite
php artisan key:generate

php artisan migrate --seed
npm run build

php artisan serve
```

للتطوير مع إعادة التحميل التلقائي للواجهة شغّل `npm run dev` في نافذة ثانية بجانب `php artisan serve` (أو `composer dev` لتشغيلهما معًا).

### Windows (PowerShell)

```powershell
composer install
npm install --ignore-scripts

Copy-Item .env.example .env
# في .env: اجعل DB_CONNECTION=sqlite وعلّق سطر DB_DATABASE
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan key:generate

php artisan migrate --seed
npm run build

php artisan serve
```

ثم افتح **http://127.0.0.1:8000** في المتصفح.

### بيانات دخول تجريبية (من الـ Seeder)

| الدور | البريد الإلكتروني | كلمة المرور |
|---|---|---|
| مالك المنصة | `admin@bakery.test` | `password` |
| صاحب مخبز (مخبز النور) | `owner@bakery.test` | `password` |

لا يوجد تسجيل ذاتي: مالك المنصة ينشئ حساب صاحب كل مخبز، وصاحب المخبز ينشئ حسابات موظفيه من صفحة "المستخدمون".

> غيّر كلمات المرور هذه فورًا في أي بيئة إنتاج حقيقية.

## الاختبارات والفحوص

قبل كل commit يجب أن تنجح الأوامر الأربعة:

```bash
php artisan test        # اختبارات PHPUnit على SQLite في الذاكرة
vendor/bin/pint --test  # تنسيق كود PHP (بدون --test يصلحه)
npx tsc --noEmit        # فحص أنواع TypeScript
npm run build           # بناء الواجهة
```

## النشر

انظر `DEPLOY.md` (Docker و Render، والتحديث من النسخة القديمة).
