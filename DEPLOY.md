# النشر

المشروع يُبنى صورة Docker واحدة (`Dockerfile`) فيها الخلفية والواجهة المبنية، ويأتي معه ملف Render Blueprint (`render.yaml`) لخدمة ويب وقاعدة PostgreSQL مجانيتين. الصورة تعمل أيضًا مع MySQL.

## ما تفعله الصورة

**عند البناء** (`Dockerfile`):

1. مرحلة Node 22: `npm ci` ثم `npm run build` (Vite يبني React و TypeScript و Tailwind إلى `public/build`).
2. مرحلة PHP 8.4: إضافات `pdo_pgsql` و `pdo_mysql` و `gd` و `zip` و `opcache` (و `mbstring` و `xml` و `dom` موجودة في الصورة الأساسية) — يحتاجها mPDF و PhpSpreadsheet لتصدير الكشوف.
3. `composer install --no-dev`، ونسخ الواجهة المبنية، وإنشاء `storage/framework/cache/mpdf` (مجلد mPDF المؤقت) وجعل `storage` و `bootstrap/cache` قابلين للكتابة.
4. خط IBM Plex Sans Arabic لملفات PDF يأتي من `resources/fonts` داخل المستودع، فلا يحتاج تنزيلًا.

**عند التشغيل** (`docker-entrypoint.sh`):

1. `php artisan migrate --force` (المهاجرات الجديدة فقط).
2. حسابات تجريبية عبر `DatabaseSeeder` إن لم يوجد مالك منصة بعد، ما لم يكن `SEED_DEMO_DATA=false`.
3. `config:cache` و `route:cache` و `view:cache`.
4. الخادم المدمج في PHP على المنفذ `PORT` (افتراضيًا 8080) بأربعة عمّال (`PHP_CLI_SERVER_WORKERS`).

### متغيرات البيئة الأساسية

| المتغير | القيمة |
|---|---|
| `APP_KEY` | **إلزامي.** يُولَّد مرة واحدة بـ `php artisan key:generate --show` ولا يُرفع إلى المستودع |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` (صفحات الأخطاء العربية 500 و 503 تظهر فقط مع `APP_DEBUG=false`) |
| `DB_CONNECTION` و `DB_HOST` و `DB_PORT` و `DB_DATABASE` و `DB_USERNAME` و `DB_PASSWORD` | `pgsql` أو `mysql` |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` |
| `SEED_DEMO_DATA` | `true` لإنشاء الحسابات التجريبية في أول تشغيل، `false` لتركيب حقيقي |

## Render (الخطة المجانية)

1. من [render.com](https://render.com): **New +** ← **Blueprint**، واربط مستودع GitHub واختر الفرع.
2. يعرض Render خدمة **bakery-app** وقاعدة **bakery-db**. قبل **Apply** يطلب قيمة `APP_KEY` (لا تُحفظ في `render.yaml`): ولّدها محليًا بـ `php artisan key:generate --show` والصقها.
3. البناء الأول يستغرق 3–5 دقائق، ثم افتح الرابط الذي يعطيه Render.
4. الحسابات التجريبية (عند `SEED_DEMO_DATA=true`): `admin@bakery.test` و `owner@bakery.test` بكلمة المرور `password`. **غيّر كلمتي المرور فورًا** من صفحة الملف الشخصي، لأن الرابط عام.

ملاحظات الخطة المجانية: الخدمة تتوقف بعد ~15 دقيقة بلا زيارات وتستيقظ في 30–60 ثانية، وقاعدة Postgres المجانية تنتهي بعد 90 يومًا.

> **تنبيه أمني:** نسخ سابقة من `render.yaml` كانت تحمل قيمة `APP_KEY` ثابتة مرفوعة في المستودع. إن كان نشرك الحالي يستخدمها فاستبدلها بمفتاح جديد من لوحة Render (سيُطلب من المستخدمين تسجيل الدخول من جديد).

كل دفع إلى الفرع المربوط يعيد النشر تلقائيًا ويشغّل المهاجرات الجديدة.

## Docker على خادم خاص

```bash
docker build -t bakery .
docker run -d -p 8080:8080 \
  -e APP_KEY=base64:... -e APP_ENV=production -e APP_DEBUG=false \
  -e DB_CONNECTION=mysql -e DB_HOST=... -e DB_DATABASE=bakery -e DB_USERNAME=... -e DB_PASSWORD=... \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e SEED_DEMO_DATA=false \
  bakery
```

ضع الخدمة خلف خادم وكيل (Nginx أو Caddy) يقدّم HTTPS.

## تحديث من النسخة القديمة

النسخة الجديدة تضيف جدول الحركات `movements` وتملؤه من المبيعات والدفعات وإيداعات الطحين القديمة (المهاجرة `2026_11_26_000002_backfill_movements.php`)، وتعكس إشارة `customers.flour_balance_kg` إلى الاصطلاح الجديد (موجب = عليه). جدولا `payments` و `flour_deposits` يبقيان بصفوفهما ولا يُكتب فيهما بعد الآن.

1. **خذ نسخة احتياطية كاملة من قاعدة البيانات قبل أي شيء:**
   ```bash
   mysqldump --single-transaction --routines -u USER -p bakery > bakery-before-update.sql
   # أو لـ PostgreSQL:
   pg_dump -Fc -d "$DATABASE_URL" -f bakery-before-update.dump
   ```
2. **جرّب التحديث على نسخة من قاعدة الإنتاج أولًا.** مهاجرة الترحيل اختُبرت على SQLite، ثم شُغّلت كل الاختبارات (ومنها `LedgerTest::test_the_migration_keeps_every_balance`) على MySQL 8.4 و PostgreSQL 17 فارغتين ونجحت؛ لكنها لم تُجرَّب على بيانات إنتاج حقيقية، فيجب تجربتها على نسخة من قاعدة الإنتاج الفعلية قبل النشر: استعد النسخة الاحتياطية في قاعدة منفصلة، وشغّل الصورة الجديدة عليها، ونفّذ الخطوة 4.
3. **انشر النسخة الجديدة.** الصورة تشغّل `php artisan migrate --force` تلقائيًا عند البدء (أو شغّله يدويًا خارج Docker). سجل الحركات الحالي يُبنى مرة واحدة. خارج Docker، إن كان مجلد `storage` باقيًا من النسخة القديمة، شغّل `php artisan optimize:clear` بعد التحديث: القوالب المترجمة القديمة تشير إلى Livewire المحذوف فيفشل تحميل كشف PDF بخطأ 500 حتى تُمسح.
4. **افحص أرصدة بعض الزبائن** بعد الترحيل: افتح صفحة عدة زبائن (من لهم طحين، ومن عليهم مال، ومن عندهم مبيعات غير مدفوعة) وقارن:
   - رصيد الطحين بالكلمات ("له X كغ" لمن كان رصيده القديم موجبًا) مع الرقم القديم.
   - الرصيد المالي مع مجموع المتبقي على مبيعاته غير المدفوعة في النسخة القديمة.
   - الرصيد الختامي في كشفي "الكل" مع الرصيد في رأس الصفحة.
   ما لا تفسّره السجلات القديمة يظهر سطر "رصيد افتتاحي" سجّله "ترحيل البيانات"، فلا يتغير معنى أي رصيد.
5. إن ظهر خلل: أوقف الخدمة واستعد النسخة الاحتياطية من الخطوة 1، ثم انشر النسخة السابقة.
