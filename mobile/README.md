# تطبيق المخبز للجوال

تطبيق React Native (Expo) لأصحاب المخابز وموظفيهم ومالك المنصة، بالعربية ومن اليمين لليسار دائمًا. القرارات والتصميم المعتمد في `CLAUDE.md` القسم 13.

**الحالة (الجلسة 11):** مربوط بالخادم الحقيقي (`/api/v1`، `docs/api.md`): الدخول بالتوكن، نسخة محلية في SQLite تعمل دون إنترنت، قائمة انتظار ومزامنة، وطباعة الإيصال. الاختبار على أجهزة وبناء EAS في الجلسة 12.

## التشغيل

المتطلبات: Node 20 أو أحدث، و npm.

```bash
cd mobile
npm install
```

### الخادم

التطبيق يحتاج خادم Laravel (جذر المستودع). عنوانه من `EXPO_PUBLIC_API_URL` (الافتراضي `http://localhost:8000/api/v1`؛ على هاتف حقيقي استخدم عنوان الحاسوب في الشبكة، مثل `http://192.168.1.20:8000/api/v1` مع `php artisan serve --host=0.0.0.0`).

```bash
# في جذر المستودع
php artisan migrate --seed      # حسابات التجربة: owner@bakery.test و admin@bakery.test بكلمة password
php artisan serve
```

### على الهاتف (Expo Go أو dev build)

```bash
npx expo start
```

- امسح رمز QR بتطبيق **Expo Go** (أندرويد) أو بالكاميرا (iPhone). الهاتف والحاسوب على نفس الشبكة، أو أضف `--tunnel`.
- كل المكتبات المستخدمة من وحدات Expo Go المدمجة (`expo-blur`، `expo-linear-gradient`، `react-native-svg`، `react-native-reanimated`)، فيعمل التطبيق في Expo Go دون بناء خاص.
- **اتجاه RTL:** التطبيق يستدعي `I18nManager.forceRTL(true)`، و`app.json` يضبط `extra.supportsRTL` و `extra.forcesRTL`. في Expo Go قد يحتاج أول تشغيل إلى إغلاق التطبيق وفتحه مرة ثانية حتى ينقلب الاتجاه؛ في dev build أو البناء النهائي يكون RTL من أول تشغيل.
- لبناء تطوير: `npx expo run:android` أو `npx expo run:ios` (يحتاج Android Studio أو Xcode). بناء EAS للمتاجر في الجلسة 12.

### على الويب

```bash
npx expo start --web            # تطوير
npx expo export -p web          # بناء ثابت في dist/
```

### دون إنترنت (لقطات الشاشة)

على الويب يرسم `?net=off` حالة دون إنترنت، و`?theme=dark|light` الوضع، و`?sheet=sale|payment|deposit|flour|adjustment|movement|customer|bank|bakery` (مع `customer=` و `movement=` رقم الحركة) يفتح نافذة سفلية، و`?toast=demo` و`?confirm=demo` الرسائل ونافذة التأكيد.

## الفحوص

```bash
npx tsc --noEmit     # الأنواع
npx expo lint        # ESLint
npm test             # قواعد العرض، الكشف، قائمة الانتظار والمزامنة (بخادم وهمي)، الإيصال
npm run test:integration   # المزامنة ضد خادم Laravel حقيقي (يشغّل php artisan serve بقاعدة SQLite جديدة)
```

## بنية المجلد

```
mobile/
  app.json                 إعداد Expo (الاسم، RTL، أيقونات)
  tailwind.config.js       NativeWind: ألوان الموقع كمتغيرات CSS من src/theme/palette.js
  src/
    app/                   الشاشات (expo-router)
      _layout.tsx          الخط، RTL، المزوّدات، وبوابتا «انتهى الاشتراك» و«لا صلاحيات»
      login.tsx
      (bakery)/            صاحب المخبز والموظف: شريط التنقل العائم بخمسة عناصر
        index.tsx          الرئيسية (أو إجراءات سريعة للموظف بلا «عرض اللوحة»)
        customers/         القائمة وصفحة الزبون (الرصيدان، الإجراءات، الكشفان، المبيعات)
        log.tsx            سجل المبيعات
        more/              المزيد، المبيعات، المزامنة، البنوك، المستخدمون، نموذج الموظف، الإعدادات، الملف الشخصي
      (admin)/             مالك المنصة: المخابز وصفحة المخبز والحساب
    components/
      ui/                  مكوّنات React Native Reusables (منسوخة من مستودعها ومُعاد تلوينها بالرموز)
      glass.tsx            مستويات الأسطح الزجاجية، البطاقة البارزة، خلفية المستوى 0
      sheet.tsx sheets.tsx النافذة السفلية وكل النماذج والتفاصيل
      nav-bar.tsx          الشريط العائم وزر البيع البارز
      ...                  الصفوف، الأزرار المتبادلة، الرسائل، لوحة الأرقام، الرسوم، التأكيد
    theme/                 palette.js (ألوان الموقع، المصدر الوحيد) و index.ts (الأسطح، الخط، المسافات)
    lib/                   format.ts (نسخة قواعد الموقع)، receipt.ts (الإيصال)، period.ts، access.ts، subscription.ts
    api/                   client.ts (الطلبات والأخطاء) و types.ts (أشكال ردود /api/v1)
    sync/                  engine.ts (النسخة المحلية والمزامنة، دوال نقية)، operations.ts (العمليات دون إنترنت)، storage.ts (SQLite) و storage.web.ts
    state/                 app.tsx (الجلسة، العمليات، useRemote)، statement.ts (الكشف المحلي)، theme.tsx
  tests/                   format، statement، sync (وحدة) و integration (خادم حقيقي)
```

## ما يحتاج جهازًا حقيقيًا

- **التغبيش على أندرويد:** `expo-blur` على أندرويد يحتاج `BlurTargetView` حول المحتوى المغبَّش؛ حاليًا يستخدم أندرويد بدائل «أندرويد الضعيف» الصلبة من النموذج. يُفحص على جهاز قبل تفعيل التغبيش.
- **الظلال** (`boxShadow`) والحافة العليا اللامعة على أندرويد و iOS.
- **RTL على iOS و أندرويد** بعد `forceRTL` (اتجاه الشريط، الأسهم، لوحة الأرقام، الرسوم).
- **«تقليل الشفافية»** في إعدادات الجهاز يبدّل إلى البدائل الصلبة.
- لوحة المفاتيح فوق النوافذ السفلية، والمناطق الآمنة على أجهزة بنتوء.
