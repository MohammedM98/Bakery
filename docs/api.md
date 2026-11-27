# واجهة تطبيق الجوال البرمجية (`/api/v1`)

واجهة خاصة بتطبيق الجوال (القسم 13 من `CLAUDE.md`)، ليست API عامًا. كل الطلبات والردود JSON، والرسائل بالعربية، وأسماء الحقول بالإنجليزية.

- **العنوان:** `https://<الخادم>/api/v1` (محليًا `http://localhost:8000/api/v1`).
- **الرؤوس:** `Accept: application/json`، و `Content-Type: application/json` للكتابة، و `Authorization: Bearer <token>` لكل ما بعد الدخول.
- **الوقت:** تواريخ الأيام `Y-m-d`، والأوقات ISO 8601. الخادم بتوقيت `UTC` (`timezone`)، ويعرض الموقع الأوقات بتوقيت `Asia/Hebron` (`local_timezone`).

## قواعد عامة

### المبالغ والنصوص الجاهزة

كل مبلغ أو كمية **نص عشري بمنزلتين** (`"30.00"`) لا رقم عائم، ومعه نص عرض جاهز بالحقل نفسه مع `_text`، مولَّد بنفس دوال الموقع (`money()` و `kg()` و `flour_balance()`)، فلا يعيد التطبيق كتابة قواعد العرض:

| النوع | الحقل | مثال `_text` |
|---|---|---|
| رصيد مالي | بإشارة: موجب = عليه، سالب = له | `"70.00 شيكل"`، `"⁦-50.00⁩ شيكل"` (السالب داخل عزل من اليسار لليمين حتى تبقى الإشارة على يساره) |
| رصيد طحين | بإشارة في الرقم، **بالكلمات** في النص | `"عليه 20 كغ"`، `"له 20 كغ"`، `"0 كغ"` — لا إشارة أبدًا |
| مبلغ أو كمية حركة | `amount` بإشارة، والنص بلا إشارة ومعه `direction_label` (`عليه` / `له`) | `"15.00 شيكل"`، `"10 كغ"` |
| مبالغ عادية (أسعار، مجاميع) | — | `"5.00 شيكل"`، `"24 كغ"` |

### الصفحات

القوائم التي تكبر مقسّمة بصيغة Laravel: `data` و `links` و `meta` (`current_page`، `last_page`، `total`...) ومعامل `?page=`. قائمتا المبيعات وسجل المبيعات داخل حقل (`sales` و `movements`) بنفس الصيغة.

### الأخطاء

كل رفض بنفس الشكل:

```json
{ "code": "validation_failed", "message": "حقل الكمية مطلوب.", "errors": { "kg_amount": ["حقل الكمية مطلوب."] } }
```

| الرمز | HTTP | المعنى |
|---|---|---|
| `validation_failed` | 422 | بيانات غير صحيحة؛ `errors` لكل حقل (نفس رسائل الموقع) |
| `unauthenticated` | 401 | لا توكن أو توكن ملغى ← شاشة الدخول |
| `account_disabled` | 401 | أوقف صاحب المخبز الحساب؛ أُلغي توكن الجهاز ← شاشة الدخول مع الرسالة |
| `forbidden` | 403 | الدور أو الصلاحية لا تسمح |
| `subscription_expired` | 403 | اشتراك المخبز منتهٍ أو موقوف ← شاشة "انتهى الاشتراك" |
| `not_found` | 404 | المسار أو السجل غير موجود (سجل مخبز آخر = غير موجود) |
| `customer_not_found` / `sale_not_found` / `bank_not_found` / `user_not_found` / `bakery_not_found` | 404 | السجل المذكور غير موجود في هذا المخبز (ربما حُذف) |
| `already_paid` | 409 | تأكيد دفع لبيع مدفوع مسبقًا |
| `idempotency_conflict` | 409 | `client_uuid` استُخدم من قبل لعملية أخرى أو ببيانات أخرى |
| `price_mismatch` | 422 | سعر البيع دون إنترنت لم يكن من أسعار المخبز في وقته |
| `unknown_operation` | 422 | نوع عملية غير معروف |
| `invalid_cursor` | 422 | مؤشر المزامنة تالف ← مزامنة كاملة من جديد |
| `too_many_requests` | 429 | طلبات كثيرة |
| `server_error` | 500 | خطأ في الخادم (في `sync/push` فقط بهذا الرمز؛ يُعاد إرساله لاحقًا) |

### منع التكرار (`client_uuid`)

- كل عملية **إنشاء أو حركة** تحمل `client_uuid` (UUID يولّده الهاتف مرة لكل عملية): البيع، البيع مقابل الطحين، الإيداع، الدفعة، تأكيد الدفع، التسوية، إضافة زبون، إضافة بنك، إضافة موظف، إضافة مخبز، تجديد الاشتراك. باقي التعديلات تقبله اختياريًا.
- يُحفظ المفتاح مع الرد في جدول `idempotency_keys` **داخل نفس الـ transaction** التي تكتب العملية. إعادة نفس المفتاح من نفس المستخدم تعيد **الرد الأول نفسه** (وفي الطلب المباشر رأس `Idempotent-Replayed: true`، وفي المزامنة الحالة `duplicate`) ولا يُكتب شيء مرة ثانية. طلبان متزامنان بنفس المفتاح: الثاني ينتظر الأول ثم يأخذ ردّه.
- نفس المفتاح بعملية أخرى أو بيانات أخرى ← `idempotency_conflict`.
- العملية المرفوضة (تحقق، صلاحية...) **لا تحفظ مفتاحها**: يمكن تصحيحها وإرسالها بنفس المفتاح.
- المفتاح خاص بالمستخدم: عند تبديل الحساب على الهاتف تُفرَّغ قائمة الانتظار أو تُرسل قبل الخروج.
- يحل محل `submission_id` الذي يستخدمه الموقع (ذاك قفل مؤقت ليوم في الـ cache).

## الدخول

### `POST /login`

```json
{ "email": "owner@bakery.test", "password": "password", "device_name": "هاتف سامي" }
```

نفس قواعد دخول الموقع (`LoginRequest`): قفل بعد 5 محاولات فاشلة لنفس البريد والعنوان، ورسالة واحدة لا تكشف هل البريد مسجَّل، والحساب الموقوف يأخذ نفس رسالة الخطأ (`422`، `errors.email`). الدخول يُقبل والاشتراك منتهٍ (كما في الموقع)، ويظهر ذلك في `subscription.active`.

الرد `200`: `token` ثم نفس حقول `GET /me`. التوكن لا ينتهي تلقائيًا (التطبيق يعمل دون إنترنت)، ويُلغى بالخروج أو بتغيير كلمة المرور أو بإيقاف الحساب.

### `POST /logout`

يلغي توكن هذا الجهاز فقط. الرد `{ "logged_out": true }`.

### `GET /me`

```json
{
  "user": { "id": 2, "name": "صاحب المخبز", "email": "owner@bakery.test", "role": "bakery_owner" },
  "bakery": { "id": 1, "name": "مخبز النور", "phone": null, "address": null },
  "subscription": { "status": "active", "active": true, "expires_at": "2026-12-27", "days_left": 30 },
  "permissions": ["view-dashboard", "view-customers", "..."],
  "prices": { "regular_price_per_kg": "5.00", "regular_price_text": "5.00 شيكل", "flour_exchange_fee_per_kg": "1.50", "flour_exchange_fee_text": "1.50 شيكل" },
  "server_time": "2026-11-27T09:00:00+00:00",
  "timezone": "UTC",
  "local_timezone": "Asia/Hebron"
}
```

`role`: `super_admin` أو `bakery_owner` أو `bakery_staff`. مالك المنصة: `bakery` و `subscription` و `prices` قيمتها `null` و `permissions` فارغة. `permissions` مفاتيح جدول البند 7 من 9-ب (صاحب المخبز يملكها كلها مع `delete-customers` و `delete-banks` و `manage-users` و `manage-settings`). التطبيق يخفي بها الأزرار فقط؛ الفحص الحقيقي في الخادم.

### `PUT /profile` و `PUT /password`

لكل الأدوار، بقواعد صفحة الملف الشخصي في الموقع. `profile`: `name`، `email` (بأحرف صغيرة، فريد) ← نفس رد `GET /me`. `password`: `current_password`، `password`، `password_confirmation`، `device_name` ← `{ "token": "..." }`: تغيير كلمة المرور يلغي كل توكنات المستخدم (هذا الجهاز أيضًا)، فيأخذ الجهاز الذي غيّرها توكنًا جديدًا ويبقى داخلًا، وتخرج أجهزته الأخرى.

## الأدوار والصلاحيات

- مسارات المخبز لـ `bakery_owner` و `bakery_staff`، ثم الاشتراك الفعّال، ثم صلاحية كل مسار (الجدول أدناه) — مثل `/panel` في الموقع. مسارات `/admin` لـ `super_admin` فقط.
- `sync/push` و `sync/pull` لا يرفضان الطلب كله بانتهاء الاشتراك: الدفع يرفض كل عملية بـ `subscription_expired`، والسحب يعيد الحالة دون بيانات.
- أي معرّف لسجل مخبز آخر = `404`.

## القراءة

| المسار | الصلاحية | المحتوى |
|---|---|---|
| `GET /dashboard?period=day\|week\|month\|year` | `view-dashboard` | أرقام اللوحة الرئيسية في الموقع نفسها: `bread` (`kg`، `regular`، `flour` + `_text`)، `takings` (`received`، `cash`، `transfer`، `unpaid` + `_text`، `paidPercent`)، `recent` (آخر 5 حركات في الفترة مع `amount_text`)، `chart` (7 فترات: `label`، `kg`، `received` أرقامًا للرسم)، `rangeLabel`، `pricePerKg` و `price_text`، `customers` (للإيداع) |
| `GET /customers?search=&page=` | `view-customers` | 30 في الصفحة، لكل زبون: `id`، `name`، `mobile_number`، `notes`، `money_balance(_text)`، `flour_balance(_text)` |
| `GET /customers/{id}?from=&to=` | `view-customers` | صفحة الزبون: `customer`، `range`، `money_statement` و `flour_statement` (كلٌّ بصلاحية كشفه وإلا `null`)، `sales` (`view-sales`)، `unpaid_sales` و `banks` (`record-payment`)، `can_adjust`، `flour_exchange_fee`. الفترة كالموقع: الافتراضي هذا الشهر حتى اليوم، و `from=&to=` فارغان = الكل |
| `GET /sales?type=cash\|flour_exchange&status=paid\|unpaid&date=&page=` | `view-sales` | `sales` (20 في الصفحة) و `filters` |
| `GET /sales-log?period=&date=&type=&customer=&recorded_by=&page=` | `view-sales-log` | سجل المبيعات في الموقع: `summary` (مع `_text`)، `movements` (30 في الصفحة، مع `direction_label` و `amount_text`)، `range`، `previousDate` / `nextDate`، `options`، و `chart` (7 فترات تنتهي بالفترة المعروضة: `label`، `kg`، `received` أرقامًا للرسم، كرسم اللوحة) |
| `GET /banks` | `view-banks` | `banks` (`id`، `name`)، `can_manage`، `can_delete` |
| `GET /users` | `manage-users` (صاحب المخبز) | `users` (`id`، `name`، `email`، `is_active`، `permissions`) و `groups` (مجموعات الصلاحيات بتسمياتها العربية) |
| `GET /settings` | `manage-settings` (صاحب المخبز) | `prices` |
| `GET /admin/bakeries?page=` | `super_admin` | 15 في الصفحة: `id`، `name`، `phone`، `address`، `subscription`، `owner` (`name`، `email`) |
| `GET /admin/bakeries/{id}` | `super_admin` | `bakery` |

**كشف الحساب** (`money_statement` / `flour_statement`) هو `Customer::statement()` نفسه الذي يبني صفحة الموقع وملفات PDF و Excel:

```json
{
  "opening": "0.00", "opening_text": "0.00 شيكل",
  "closing": "-20.00", "closing_text": "⁦-20.00⁩ شيكل",
  "rows": [
    { "number": 2, "date": "2026-11-27", "description": "رسم تبديل طحين 20 كغ × 1.50 شيكل", "note": null,
      "transfer": null, "transfer_reference": null, "debit": "30.00", "credit": null, "balance": "30.00",
      "recorded_by": "صاحب المخبز", "debit_text": "30.00 شيكل", "credit_text": null, "balance_text": "30.00 شيكل" }
  ]
}
```

في كشف الطحين `debit_text` / `credit_text` بالكيلو (`"20 كغ"`) و `balance_text` بالكلمات (`"عليه 20 كغ"`). `transfer` للدفعات: `"نقدًا"` أو `"جوال باي ← بنك فلسطين"`.

## الكتابة

كل عملية تمر عبر `App\Http\Api\Operations`: نفس Form Request الموقع (القواعد والرسائل العربية و `authorize()`)، ونفس البوابة، ونفس الـ Action داخل transaction. نفس العمليات تُرسل مباشرة بالمسارات أدناه أو من قائمة الانتظار في `sync/push` (العمليات المسموحة دون إنترنت فقط). المعرّفات في المسار تغلب ما في الجسم.

| المسار | `type` | الصلاحية | الجسم (غير `client_uuid`) | الرد |
|---|---|---|---|---|
| `POST /sales` | `sale` ★ | `record-sale` | `kg_amount`، `payment_status` (`paid`/`unpaid`)، `sale_date`، `buyer_name?`، `buyer_mobile?`، `notes?` (+ `price_per_kg` في المزامنة) | 201 حركة |
| `POST /customers/{customer_id}/flour-sales` | `flour_sale` ★ | `record-flour-sale` | `kg_amount`، `payment_status`، `sale_date`، `notes?` (+ `price_per_kg` = رسم التبديل في المزامنة) | 201 حركة |
| `POST /customers/{customer_id}/deposits` | `deposit` ★ | `record-deposit` | `kg_amount`، `deposit_date`، `notes?` | 201 حركة |
| `POST /customers/{customer_id}/payments` | `payment` ★ | `record-payment` | `amount`، `payment_date`، `sale_id?`، `notes?`، `method` (`cash` افتراضيًا / `transfer`)، `from_bank_id` و `to_bank_id` (للتحويل)، `transfer_reference?` | 201 حركة |
| `POST /customers/{customer_id}/adjustments` | `adjustment` ★ | `record-adjustment` | `ledger` (`money`/`flour`)، `direction` (`debit` = عليه / `credit` = له)، `amount`، `reason`، `movement_date`، `corrects_number?` | 201 حركة |
| `POST /sales/{sale_id}/mark-paid` | `mark_paid` | `record-payment` | — | 200 حركة، أو 409 `already_paid` |
| `POST /customers` | `customer.create` ★ | `create-customers` | `name`، `mobile_number`، `notes?`، `flour_balance_kg?` (يحتاج `record-deposit`) | 201 `customer` و `movements` |
| `PUT /customers/{customer_id}` | `customer.update` | `edit-customers` | `name`، `mobile_number`، `notes?` | 200 `customer` |
| `DELETE /customers/{customer_id}` | `customer.delete` | صاحب المخبز | — | 200 `{deleted: true}` (مع كل سجلاته، كالموقع) |
| `POST /banks` | `bank.create` | `manage-banks` | `name` | 201 `bank` |
| `PUT /banks/{bank_id}` | `bank.update` | `manage-banks` | `name` | 200 `bank` |
| `DELETE /banks/{bank_id}` | `bank.delete` | صاحب المخبز | — | 200 `{deleted: true}` |
| `PUT /settings` | `settings.update` | صاحب المخبز | `regular_price_per_kg`، `flour_exchange_fee_per_kg` | 200 `prices` |
| `POST /users` | `staff.create` | صاحب المخبز | `name`، `email`، `password`، `permissions` (مصفوفة مفاتيح) | 201 `user` |
| `PUT /users/{user_id}` | `staff.update` | صاحب المخبز | `name`، `email`، `password?` (فارغ = بلا تغيير؛ تغييرها يلغي توكنات أجهزته)، `permissions` | 200 `user` |
| `POST /users/{user_id}/status` | `staff.status` | صاحب المخبز | `active` (الحالة المطلوبة لا قلب) | 200 `user` |
| `POST /admin/bakeries` | `bakery.create` | `super_admin` | `name`، `phone?`، `address?`، `subscription_months` (1–24)، `owner_name`، `owner_email`، `owner_password` | 201 `bakery` |
| `PUT /admin/bakeries/{bakery_id}` | `bakery.update` | `super_admin` | `name`، `phone?`، `address?` | 200 `bakery` |
| `POST /admin/bakeries/{bakery_id}/renew` | `bakery.renew` | `super_admin` | `months` (1–24) — `client_uuid` إلزامي حتى لا يُضاف التجديد مرتين | 200 `bakery` |
| `POST /admin/bakeries/{bakery_id}/status` | `bakery.status` | `super_admin` | `status` (`active`/`inactive`) | 200 `bakery` |

★ = مسموحة دون إنترنت (تُرسل من قائمة الانتظار). حذف المخبز يبقى في الموقع فقط.

**رد "حركة"** — أرقام الحركات التي أعطاها الخادم والرصيدان بعدها:

```json
{
  "movements": [
    { "number": 41, "customer_id": 7, "sale_id": 19, "ledger": "flour", "type": "flour_withdrawal", "type_label": "بيع مقابل طحين",
      "date": "2026-11-27", "amount": "10.00", "direction": "debit", "direction_label": "عليه", "amount_text": "10 كغ",
      "description": "خبز مقابل طحين 10 كغ", "note": null, "transfer": null, "transfer_reference": null,
      "recorded_by": "سامي الموظف", "created_at": "2026-11-27T09:01:12+00:00" },
    { "number": 42, "ledger": "money", "type": "exchange_fee", "amount": "15.00", "amount_text": "15.00 شيكل", "...": "..." }
  ],
  "customer": { "id": 7, "name": "أحمد", "mobile_number": "0591111111", "notes": null,
    "money_balance": "15.00", "money_balance_text": "15.00 شيكل", "flour_balance": "10.00", "flour_balance_text": "عليه 10 كغ" },
  "sale": { "id": 19, "sale_type": "flour_exchange", "kg_amount": "10.00", "price_per_kg": "1.50", "total_amount": "15.00",
    "remaining_amount": "15.00", "payment_status": "unpaid", "payment_status_label": "غير مدفوع", "kg_text": "10 كغ", "total_text": "15.00 شيكل", "...": "..." }
}
```

`customer` قيمته `null` لبيع المشتري العابر، و `sale` قيمته `null` لغير البيع. `movements` قد تكون فارغة (زبون جديد بلا رصيد افتتاحي، أو بيع بسعر صفر).

### محتوى الإيصال (الطباعة بالإنترنت)

بعد أن يؤكد الخادم العملية: اسم المخبز (`me.bakery.name`)، التاريخ والوقت (`movements[].created_at`)، رقم الحركة (`movements[].number`)، الزبون (`customer.name`) أو `sale.buyer` ("عميل نقدي" للعابر)، الكمية والسعر والمبلغ (`sale.kg_text`، `sale.price_per_kg`، `sale.total_text`)، الرصيد بعد العملية (`customer.money_balance_text` و `customer.flour_balance_text`)، ومن سجّلها (`movements[].recorded_by`).

## المزامنة دون إنترنت

### `POST /sync/push`

قائمة الانتظار بترتيبها، حتى 100 عملية في الطلب:

```json
{
  "operations": [
    { "client_uuid": "4f0c...", "type": "customer.create", "client_created_at": "2026-11-27T10:58:00+02:00",
      "payload": { "name": "زبون جديد", "mobile_number": "0593333333" } },
    { "client_uuid": "9a1e...", "type": "flour_sale", "client_created_at": "2026-11-27T11:02:00+02:00",
      "payload": { "customer_client_uuid": "4f0c...", "kg_amount": "10", "payment_status": "unpaid", "sale_date": "2026-11-27", "price_per_kg": "1.50" } },
    { "client_uuid": "c33b...", "type": "payment", "client_created_at": "2026-11-27T11:03:00+02:00",
      "payload": { "customer_id": 7, "amount": "20", "payment_date": "2026-11-27", "method": "transfer", "from_bank_id": 2, "to_bank_id": 3 } }
  ]
}
```

- `type`: `sale`، `flour_sale`، `deposit`، `payment`، `adjustment`، `customer.create` فقط. غيرها يُرفض (`validation_failed`) لأنه بالإنترنت فقط.
- `payload`: نفس جسم المسار المباشر، ومعه معرّف الزبون: `customer_id` (معرّف الخادم) أو `customer_client_uuid` (الـ `client_uuid` لعملية `customer.create` أُرسلت قبلها، من أي جهاز في المخبز).
- كل عملية **وحدها**: transaction خاصة، وفحص الدور والاشتراك والصلاحية وقت الإرسال، ونفس التحقق. رفض عملية لا يوقف ما بعدها. الخادم وحده يعطي أرقام الحركات.

الرد `200`، نتيجة لكل عملية بنفس الترتيب:

```json
{
  "results": [
    { "client_uuid": "4f0c...", "status": "accepted", "data": { "customer": { "id": 31, "...": "..." }, "movements": [] } },
    { "client_uuid": "9a1e...", "status": "duplicate", "data": { "movements": [ { "number": 88, "...": "..." } ], "customer": { "...": "..." }, "sale": { "...": "..." } } },
    { "client_uuid": "c33b...", "status": "rejected", "code": "customer_not_found", "message": "الزبون غير موجود، ربما حُذف.", "retryable": false }
  ]
}
```

| `status` | ماذا يفعل التطبيق |
|---|---|
| `accepted` | يحذف العملية من قائمة الانتظار، ويستبدل الرصيد المؤقت بما في `data.customer` والأرقام بـ `data.movements[].number` |
| `duplicate` | طُبّقت من قبل (ضاع الرد): نفس `accepted` بالرد الأصلي |
| `rejected` | `retryable: true` (`server_error`، `too_many_requests`): تبقى في القائمة وتُعاد لاحقًا. غير ذلك: تظهر في شاشة المزامنة بـ `message` (و `errors` للتحقق) ولا تُحذف بصمت. الرموز المتوقعة: `customer_not_found` (حُذف الزبون)، `forbidden` (سُحبت الصلاحية)، `subscription_expired`، `validation_failed`، `price_mismatch` |

الطلب كله يُرفض فقط إذا لم يكن `operations` مصفوفة (أو تجاوزت 100)، أو كان التوكن أو الدور غير صالح.

### قاعدة السعر دون إنترنت

البيع (`sale`) والبيع مقابل الطحين (`flour_sale`، السعر = رسم التبديل) من قائمة الانتظار يُسجَّل **بالسعر الذي أرسله الهاتف** في `price_per_kg`، بشرط أن يكون سعرًا كان للمخبز فعلًا:

- كل تغيير لسعر الكيلو أو رسم التبديل (من الموقع أو التطبيق أو إنشاء المخبز) يُكتب في جدول `bakery_prices` بوقته. الأسعار الحالية لكل مخبز رُحّلت إليه بتاريخ إنشائه.
- يُقبل السعر إن كان **ساريًا وقت `client_created_at`** (مع 5 دقائق لفرق ساعة الهاتف)، أو كان ساريًا حتى **14 يومًا قبله** على الأكثر (هاتف بقي دون إنترنت عبر تغيير السعر). غير ذلك ← `price_mismatch`، وكذلك سعر لم يكن للمخبز أبدًا.
- 0 / 0 لمخبز جديد لم تُضبط أسعاره بعد لا يُعدّ سعرًا، والسعر 0 يُقبل فقط إن كان السعر الحالي.
- `client_created_at` أقدم من 14 يومًا مع سعر ← `price_mismatch` (لا يُوثق بوقت قديم يختاره الهاتف)؛ تُرسل دون `price_per_kg` فتُسجَّل بالسعر الحالي.
- `client_created_at` بعد وقت الخادم يُعامَل كالآن.
- دون `price_per_kg`: السعر الحالي. **في الطلب المباشر** (`POST /sales`...) يُستخدم السعر الحالي دائمًا ويُتجاهل أي سعر مرسَل، كالموقع.

### `GET /sync/pull?since=<cursor>`

ما تغيّر منذ المؤشر، للبيانات التي يحفظها الهاتف:

```json
{
  "user": {}, "bakery": {}, "subscription": {}, "permissions": [], "prices": {}, "server_time": "...", "timezone": "UTC", "local_timezone": "Asia/Hebron",
  "cursor": "1532.a1b2c3d4.20260829",
  "has_more": false,
  "full": false,
  "customers": [ { "id": 7, "name": "أحمد", "money_balance": "15.00", "flour_balance": "10.00", "...": "..." } ],
  "movements": [ { "number": 88, "customer_id": 7, "ledger": "flour", "...": "..." } ],
  "banks": [ { "id": 2, "name": "جوال باي" } ],
  "deleted": { "customers": [12], "banks": [5] }
}
```

- **دائمًا:** المستخدم وصلاحياته وحالة الاشتراك والأسعار ووقت الخادم (نفس `GET /me`).
- `customers`: الزبائن الذين تغيّروا (تعديل، أو حركة غيّرت رصيدهم) مع رصيديهم — يُستبدلون كاملين في النسخة المحلية. تحتاج `view-customers`.
- `movements`: حركات الزبائن الجديدة (لا حركات المشتري العابر)، لكشف كل زبون دون إنترنت، من الدفترين اللذين يملك المستخدم كشفيهما فقط (`view-money-statement`، `view-flour-statement`). الحركات لا تتغير ولا تُحذف، فتُضاف فقط (مفتاحها `number`).
- `banks`: البنوك المضافة أو المعدّلة (تحتاج `view-banks` أو `record-payment`).
- `deleted`: معرّفات الزبائن والبنوك المحذوفة: يحذفها الهاتف، ومع الزبون كل حركاته.
- **أول مزامنة** (بلا `since`) تجلب كل الزبائن والبنوك، والحركات لآخر **90 يومًا**. الكشف الأقدم يُقرأ بالإنترنت من `GET /customers/{id}`. "رصيد سابق" لكشف محلي = رصيد الزبون − مجموع حركاته المحفوظة بعد تاريخ البداية.
- `has_more: true`: يُطلب فورًا مرة أخرى بالمؤشر الجديد حتى يصير `false` (حتى 500 حركة في الصفحة). كل صفحة متسقة حتى مؤشرها.
- `full: true`: هذه مزامنة كاملة (لا مؤشر، أو تغيّرت صلاحيات المستخدم منذ المؤشر): يُفرَّغ ما حُفظ من زبائن وحركات وبنوك قبل تطبيقها.
- الاشتراك منتهٍ: لا بيانات، و `subscription.active = false`، والمؤشر يعود كما أُرسل.
- العمليات التي تنتظر في الهاتف: رصيدها المحلي "مؤقت ينتظر المزامنة" حتى يقبلها `sync/push`. الترتيب المقترح: `push` ثم `pull`.

**المؤشر** نص معتم بالشكل `{version}.{permissions}.{floor}`:

- `version`: رقم تغيير متزايد لكل مخبز (`bakeries.sync_version`). كل كتابة على زبون أو بنك أو حركة تأخذ الرقم التالي **تحت قفل صف المخبز داخل نفس الـ transaction** (كما يُعطى رقم الحركة)، ويُختم به الصف (`sync_version`) أو شاهد الحذف (`sync_tombstones`). لذلك عندما يقرأ الخادم الرقم الحالي لا يمكن أن يُلتزم لاحقًا صف برقم أقل منه: لا يفوت الهاتفَ تغيير. كل الحركات من عملية واحدة تأخذ نفس الرقم، فلا تنقسم بين صفحتين.
- `permissions`: بصمة صلاحيات المستخدم؛ تغيّرها يبدأ مزامنة كاملة بما يحق له رؤيته الآن.
- `floor`: بداية تاريخ الحركات المحفوظة (`Ymd`)، تبقى ثابتة بعد المزامنة الكاملة.
