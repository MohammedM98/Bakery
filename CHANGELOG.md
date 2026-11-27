# Release Notes

## [Unreleased](https://github.com/laravel/laravel/compare/v13.7.0...13.x)

### قرارات صاحب المشروع (بعد الجلسة 10)

* التاريخ صار بيوم المخبز بتوقيت فلسطين: البيع بعد منتصف الليل يُؤرَّخ بيومه الصحيح لا باليوم السابق، و«اليوم» في اللوحة والسجل والكشوف والاشتراك كذلك.
* نافذة حذف الزبون تذكر رصيده المالي ورصيد الطحين إن لم يكونا صفرًا، وتنبّه أنهما يضيعان بالحذف.
* أُزيل «حذف الحساب» من الملف الشخصي لكل الأدوار.
* كل دور يتلقى في المتصفح أسماء مساراته فقط.
* النشر الجديد لا ينشئ حسابات تجريبية؛ مالك المنصة الأول من `ADMIN_EMAIL` و `ADMIN_PASSWORD`.

### واجهة تطبيق الجوال البرمجية (الجلسة 9)

* واجهة خاصة لتطبيق الجوال على `/api/v1` (`docs/api.md`): الدخول بتوكن لكل جهاز بنفس قواعد دخول الموقع، وقراءة اللوحة والزبائن وكشوفهم والمبيعات وسجل المبيعات والبنوك والموظفين والإعدادات والمخابز، وكل عمليات الموقع بنفس الصلاحيات.
* كل عملية من التطبيق تحمل معرّفًا فريدًا فلا تُسجَّل مرتين أبدًا، حتى لو أُعيد إرسالها.
* المزامنة دون إنترنت: إرسال قائمة العمليات المنتظرة (نتيجة لكل عملية وسبب الرفض بالعربية) وجلب ما تغيّر منذ آخر مزامنة مع الزبائن والبنوك المحذوفة.
* البيع دون إنترنت يُسجَّل بالسعر الذي كان على الهاتف إن كان من أسعار المخبز في وقته؛ كل تغيير للأسعار صار يُحفظ في سجل.
* تغيير كلمة مرور موظف يُخرج كل أجهزته من التطبيق.

### واجهة جديدة (الجلسة 1)

* صفحات تسجيل الدخول واستعادة كلمة المرور وتعيينها وتأكيدها وتأكيد البريد صارت بواجهة React جديدة، بقسمين على سطح المكتب وعمود واحد على الجوال، وزر الدخول يُعطَّل أثناء الإرسال.
* صفحة الملف الشخصي الجديدة: تعديل الاسم والبريد، تغيير كلمة المرور، وحذف الحساب بعد نافذة تأكيد.
* ألوان المخابز الجديدة (قشرة الخبز والقمح) مع وضع داكن وزر للتبديل بين الفاتح والداكن.
* شريط جانبي داكن على اليمين يُطوى إلى أيقونات، وقائمة منبثقة على الجوال، وقائمة المستخدم فيها الملف الشخصي وتسجيل الخروج.
* رسائل نجاح منبثقة بالعربية بعد حفظ الملف الشخصي وتغيير كلمة المرور.
* نموذج "نسيت كلمة المرور" لم يعد يكشف هل البريد مسجَّل.

### واجهة جديدة (الجلسة 2)

* لوحة مالك المنصة بواجهة جديدة: قائمة المخابز مع حالة الاشتراك (فعّال، ينتهي قريبًا، منتهٍ، موقوف) والأيام المتبقية، وبطاقات بدل الجدول على الجوال.
* إضافة مخبز مع حساب صاحبه، وصفحة تعديل فيها بيانات المخبز والاشتراك وحساب صاحبه وحذف المخبز.
* تجديد الاشتراك من نافذة باختيارات سريعة (1، 3، 6، 12 شهرًا) أو عدد حر حتى 24 شهرًا، من القائمة أو من صفحة المخبز.
* إيقاف الاشتراك وتفعيله لم يعد ينعكس عند الضغط مرتين.
* صفحة إعدادات المخبز الجديدة: سعر الكيلو ورسم تبديل القمح بحقول بالشيكل.
* قائمة العملاء الجديدة مع بحث فوري بالاسم أو الجوال، وصفحتا إضافة العميل وتعديله.
* حذف العميل يحذف الآن مبيعاته أيضًا مع دفعاته واستلامات القمح، كما تقول رسالة التأكيد.

### واجهة جديدة (الجلسة 3)

* صفحة المبيعات الجديدة بتبويبين (البيع النقدي، البيع مقابل القمح)، مع تصفية بالحالة والتاريخ، وبطاقات على الجوال.
* تسجيل البيع النقدي من نافذة في اللوحة الرئيسية وصفحة المبيعات: تكتب الكمية وتحفظ، و"مدفوع" واليوم افتراضيان، مع معاينة الإجمالي.
* صفحة "تسجيل عملية بيع" المستقلة تبقى مفتوحة بعد الحفظ لتسجيل البيع التالي.
* الضغط مرتين على زر الحفظ لا يسجّل العملية مرتين.
* "تأكيد الدفع" لعملية غير مدفوعة أو مدفوعة جزئيًا مع نافذة تأكيد، ورسالة واضحة إن كانت مدفوعة مسبقًا.
* صفحة العميل الجديدة: رصيد القمح والمبلغ المستحق، وتبويبات للمبيعات واستلام القمح والدفعات، ونوافذ لاستلام القمح وتسليم الخبز مقابل القمح وتسجيل دفعة (مع زر "كامل المستحق").
* اللوحة الرئيسية الجديدة: خبز اليوم برقم كبير مع رسم لآخر 7 أيام، غير المدفوع اليوم، المقبوضات وعدد العمليات، العمليات غير المدفوعة مع تأكيد الدفع، وآخر العمليات.
* صفحة "الاشتراك غير مفعّل" والصفحة الترحيبية بالواجهة الجديدة.
* تسجيل الدخول والخروج صارا انتقالًا مباشرًا دون إعادة تحميل الصفحة.
* فتح صفحة عميل من مخبز آخر يعطي "غير موجود" (404).

### كشوف الحساب والحركات (الجلسة 4)

* كل عملية (بيع، بيع مقابل طحين، إيداع طحين، دفعة، تأكيد دفع، رصيد افتتاحي، قيد تسوية) تُسجَّل حركة برقم تسلسلي خاص بالمخبز واسم من سجّلها، ولا تُعدَّل ولا تُحذف.
* صفحة الزبون: كشف الحساب المالي وكشف الطحين في تبويبين، كلٌّ برصيده الجاري، مع "رصيد سابق" والرصيد الختامي، واختيار الفترة (من / إلى) واختصارات: هذا الشهر، آخر 3 أشهر، السنة، الكل. بطاقات على الجوال وجدول على الشاشة العريضة.
* رصيد الطحين يُكتب بالكلمات في كل مكان: "له 20 كغ" بالأخضر، "عليه 20 كغ" بالأحمر، "0 كغ". الرصيد المالي بالإشارة: سالب له، موجب عليه.
* تسليم الخبز مقابل الطحين لم يعد يُرفض عند نقص الرصيد؛ يصبح الرصيد "عليه"، ويُخصم من أول إيداع لاحق. النافذة تعرض الرصيد بعد التسليم.
* رسم التبديل يظهر "عليه" في الكشف المالي، والدفع سطر "له" مستقل.
* الدفعة يمكن أن تكون "دفعة على الحساب" أو "دفعة عن بيع" محدد من المبيعات غير المدفوعة، ويجوز أن تزيد عمّا على الزبون فيصبح رصيده له. زر "تسجيل دفعة" ظاهر دائمًا.
* قيد التسوية الجديد (لصاحب المخبز): على الكشف المالي أو كشف الطحين، عليه أو له، بسبب إلزامي، وربط اختياري برقم الحركة المصحَّحة.
* الأرصدة الحالية نُقلت إلى الحركات دون تغيير معناها؛ ما لا تفسّره السجلات القديمة يظهر سطر "رصيد افتتاحي".
* لا يمكن تسجيل بيع أو إيداع أو دفعة أو تسوية بتاريخ في المستقبل.
* تسمية "القمح" صارت "الطحين" في كل الواجهة.

### البنوك والتحميل وسجل المبيعات (الجلسة 5)

* صفحة "البنوك والمحافظ": إضافة بنك أو محفظة، تعديل الاسم، وحذف نهائي بعد نافذة تأكيد.
* الدفعة صارت نقدًا أو تحويلًا: للتحويل حقل واحد "من ← إلى" من نفس القائمة ورقم حوالة اختياري، ويظهران في الكشف المالي. حذف البنك أو تغيير اسمه لا يغيّر الدفعات المسجلة.
* بحث في الكشفين برقم الحركة أو البيان أو رقم الحوالة.
* زرّا "تحميل PDF" و"تحميل Excel" في أعلى كل كشف، للفترة المعروضة نفسها، مع رأس فيه المخبز والزبون وجواله ونوع الكشف والفترة وتاريخ الإصدار. PDF عربي من اليمين لليسار بخط Cairo على A4، و Excel ورقة من اليمين لليسار.
* صفحة "سجل المبيعات": يوم / أسبوع (السبت إلى الجمعة) / شهر / سنة مع التنقل للفترة السابقة والتالية، بطاقات الخبز المباع والدخل المالي (نقدًا / تحويل) والطحين المصروف والمستلم، وجدول الحركات مع تصفية بالنوع والزبون ومن سجّلها.

### تطبيق الجوال ببيانات تجريبية (الجلسة 10)

* تطبيق جوال جديد لأندرويد و iPhone في مجلد `mobile/` (Expo)، بالعربية ومن اليمين لليسار، بألوان الموقع وأسطح زجاجية مغبّشة ووضعين فاتح وداكن.
* كل الشاشات تعمل الآن على بيانات تجريبية دون اتصال بالخادم: الدخول، الرئيسية بالفترات (يوم، أسبوع، شهر، سنة) وبطاقة الخبز المباع والرسم، تسجيل البيع من لوحة أرقام في نافذة سفلية، الزبائن وصفحة الزبون بالرصيدين والكشفين والمبيعات، الدفعة والإيداع والبيع مقابل الطحين وقيد التسوية، تفاصيل الحركة مع الطباعة، المبيعات، سجل المبيعات، المزامنة، البنوك، المستخدمون وصلاحياتهم، الإعدادات، الملف الشخصي، ولوحة مالك المنصة بالمخابز والتجديد والإيقاف.
* شريط تنقل عائم بزر بيع برتقالي في المنتصف، وعناصره حسب صلاحيات المستخدم.
* العمليات المسجّلة دون إنترنت تظهر بعلامة «ينتظر المزامنة» ورصيد مؤقت، وما يحتاج إنترنت يظهر معطّلًا مع السبب.
* شاشات «انتهى الاشتراك» و«لا توجد صلاحيات بعد» و«لا توجد بيانات على هذا الجهاز بعد»، ورسائل عائمة أعلى الشاشة، ونافذة تأكيد قبل كل حذف.

### التصميم المعتمد (الجلسة 8)

* كل الصفحات بالتصميم الذي اعتمده صاحب المشروع: خلفية باردة هادئة، بطاقات بيضاء بزوايا كبيرة، والبرتقالي لمسة فقط (الزر الأساسي والعنصر المختار والقيمة الحالية في الرسوم)، بالوضعين الفاتح والداكن.
* التطبيق داخل إطار مستدير على سطح المكتب مع شريط جانبي نحيف داكن بالأيقونات واسم الصفحة عند المرور. على الجوال شريط سفلي بالعناصر الرئيسية وزر "المزيد" للقائمة كاملة.
* اللوحة الرئيسية الجديدة بأزرار الفترة (يوم / أسبوع / شهر / سنة) تغيّر كل الأرقام: الخبز المباع (عادي ومقابل طحين)، آخر الحركات بأيقونة لكل نوع، المقبوضات مع نسبة المدفوع من المبيعات والدخل نقدًا وتحويلًا وغير المدفوع، ورسما الخبز المباع والدخل المالي لآخر 7 أيام / أسابيع / أشهر / سنوات.
* "إيداع طحين" من اللوحة الرئيسية مباشرة مع اختيار الزبون.
* إضافة الزبون وتعديله، وإضافة الموظف وتعديل صلاحياته، وإضافة المخبز وتعديل بياناته صارت في نوافذ منبثقة بدل صفحات منفصلة. حفظ تعديل الزبون يعود للصفحة التي فُتحت منها النافذة.
* صفحة الزبون: بطاقة داكنة بالرصيدين والإجراءات السريعة، وبطاقة بياناته.
* منتقي تاريخ عربي (تقويم يبدأ السبت) بدل حقول التاريخ الإنجليزية في كل النماذج والكشوف والتصفية.
* أزرار الفترة نفسها في سجل المبيعات واختصارات فترة الكشف وتبويبات صفحة الزبون والمبيعات.
* صفحات الدخول والزائر والخطأ وانتهاء الاشتراك بنفس الإطار مع لوحة جانبية داكنة.
* الخط الجديد IBM Plex Sans Arabic في الواجهة وملفات PDF، بارتفاع سطر أنسب للعربية.
* زر لإظهار كلمة المرور أو إخفائها في كل حقول كلمة المرور.
* البرتقالي أغمق قليلًا فصار نص الأزرار الأساسية أوضح.
* حُذفت صفحات الإضافة والتعديل المنفصلة للزبون والموظف والمخبز؛ كلها في نوافذ.
* المبالغ تُكتب بالكلمة "شيكل" بدل الرمز ₪ في الشاشات والكشوف المحمَّلة والبيانات (ومنها بيانات الحركات المسجلة سابقًا)، والرقم السالب يبقى بإشارته على اليسار: `-50.00 شيكل`.

### التنظيف والمراجعة النهائية (الجلسة 7)

* صفحات أخطاء عربية (لا تملك صلاحية، الصفحة غير موجودة، انتهت صلاحية الصفحة، خطأ غير متوقع، الصيانة) مع زر يعيد كل مستخدم إلى صفحته الرئيسية.
* "مقبوضات اليوم" ورسم آخر 7 أيام في اللوحة صارت تعرض المال الذي دخل فعلًا (الدفعات)، فتطابق "الدخل المالي" في سجل المبيعات.
* لم يعد تسجيل عمليتين في نفس اللحظة (مثلًا موظفان يسجّلان بيعًا ودفعة) يفشل أحيانًا بخطأ على MySQL.
* الضغط مرتين على "حفظ العميل" لا ينشئ الزبون مرتين.
* سعر الكيلو ورسم التبديل حتى 999.99 ₪، والرصيد الافتتاحي حتى 100,000 كغ، والكميات والمبالغ بمنزلتين عشريتين على الأكثر، برسائل خطأ عربية بدل خطأ في الخادم.
* حُذفت الواجهة القديمة (Livewire و Blade) بالكامل، وحُدّثت المكتبات لسد ثغرات أمنية معروفة.
* عنوان الصفحة الترحيبية لم يعد يكرر اسم المنصة.
* ملف النشر (Docker) صار يبني الواجهة الجديدة ويدعم تصدير PDF و Excel، و `README.md` و `DEPLOY.md` محدّثان بالعربية مع خطوات التحديث من النسخة القديمة.

### مستخدمو المخبز والصلاحيات (الجلسة 6)

* صفحة "المستخدمون" لصاحب المخبز: إضافة موظف (اسم، بريد، كلمة مرور)، تعديله وتغيير كلمة مروره، وإيقاف حسابه أو تفعيله بعد نافذة تأكيد. بطاقات على الجوال وجدول على الشاشة العريضة.
* لكل موظف صلاحيات بمربعات اختيار مجمّعة (اللوحة، الزبائن، المبيعات، المال، الطحين، الكشوف، السجل، التسوية، البنوك). الموظف الجديد بلا أي صلاحية.
* الموظف لا يرى في القائمة الجانبية ولا في الصفحات إلا ما يملك صلاحيته، وفتح أي رابط آخر مباشرة يُرفض. إن لم تكن له اللوحة الرئيسية يُفتح له أول قسم مسموح له، أو صفحة "لا توجد صلاحيات".
* حذف الزبائن والبنوك، وإدارة المستخدمين، والأسعار وإعدادات المخبز تبقى لصاحب المخبز وحده.
* كل حركة يسجّلها الموظف تظهر باسمه في الكشوف وسجل المبيعات، وتبقى باسمه بعد إيقافه.
* الموظف الموقوف لا يستطيع الدخول، ويُخرَج من النظام في طلبه التالي إن كان داخلًا.
* صفحة الزبون تعرض كشف الحساب المالي وكشف الطحين والمبيعات وأزرار الإيداع والتسليم والدفعة والتسوية والتحميل حسب صلاحيات المستخدم.
* الموظف لا يستطيع حذف حسابه من الملف الشخصي.
* مالك المنصة يرى صاحب المخبز فقط في صفحة المخبز، وحذف المخبز يحذف حسابات موظفيه أيضًا.

## [v13.7.0](https://github.com/laravel/laravel/compare/v13.6.0...v13.7.0) - 2026-05-14

**Full Changelog**: https://github.com/laravel/laravel/compare/v13.6.0...v13.7.0

## [v13.6.0](https://github.com/laravel/laravel/compare/v13.5.0...v13.6.0) - 2026-05-11

* Remove Pdo/Mysql const workaround by [@jnoordsij](https://github.com/jnoordsij) in https://github.com/laravel/laravel/pull/6810

## [v13.5.0](https://github.com/laravel/laravel/compare/v13.4.0...v13.5.0) - 2026-04-30

* Use the Vite font plugin for application fonts by [@WendellAdriel](https://github.com/WendellAdriel) in https://github.com/laravel/laravel/pull/6806

## [v13.4.0](https://github.com/laravel/laravel/compare/v13.3.0...v13.4.0) - 2026-04-28

* Add @no_additional_args to composer test script config clear by [@jnoordsij](https://github.com/jnoordsij) in https://github.com/laravel/laravel/pull/6799
* [13.x] Adds pao by default by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6802

## [v13.3.0](https://github.com/laravel/laravel/compare/v13.2.0...v13.3.0) - 2026-04-16

* [13.x] enable npm audit by default by [@leo95batista](https://github.com/leo95batista) in https://github.com/laravel/laravel/pull/6788
* Update changelog link to Laravel framework repo by [@Rattone](https://github.com/Rattone) in https://github.com/laravel/laravel/pull/6790
* [13x] Add .codex to .gitignore by [@amdad121](https://github.com/amdad121) in https://github.com/laravel/laravel/pull/6793

## [v13.2.0](https://github.com/laravel/laravel/compare/v13.1.2...v13.2.0) - 2026-04-09

* Remove axios and enable ignore-scripts by [@WendellAdriel](https://github.com/WendellAdriel) in https://github.com/laravel/laravel/pull/6778
* Add /.cursor/ to .gitignore by [@workwithbinu](https://github.com/workwithbinu) in https://github.com/laravel/laravel/pull/6782
* Remove '.fleet' from .gitignore by [@dominiq007](https://github.com/dominiq007) in https://github.com/laravel/laravel/pull/6783
* Support all compose file naming conventions in editorconfig by [@mmachatschek](https://github.com/mmachatschek) in https://github.com/laravel/laravel/pull/6786

## [v13.1.2](https://github.com/laravel/laravel/compare/v13.1.1...v13.1.2) - 2026-03-31

* Prevents installed package from executing malicious code via `postinstall` by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/laravel/pull/6777
* Add missing comma in axios by [@aziyan99](https://github.com/aziyan99) in https://github.com/laravel/laravel/pull/6779

## [v13.1.1](https://github.com/laravel/laravel/compare/v13.1.0...v13.1.1) - 2026-03-31

* Update .gitignore by [@Cegem-360](https://github.com/Cegem-360) in https://github.com/laravel/laravel/pull/6774
* [security] pin axios version by [@NickSdot](https://github.com/NickSdot) in https://github.com/laravel/laravel/pull/6776

## [v13.1.0](https://github.com/laravel/laravel/compare/v12.12.2...v13.1.0) - 2026-03-18

* Change back minimum-stability to stable by [@jnoordsij](https://github.com/jnoordsij) in https://github.com/laravel/laravel/pull/6766
* Vite 8 support

## [v12.12.2](https://github.com/laravel/laravel/compare/v12.12.1...v12.12.2) - 2026-03-14

* [12.x] Add `APP_NAME` fallback in Slack log channel username by [@hamedelasma](https://github.com/hamedelasma) in https://github.com/laravel/laravel/pull/6762

## [v12.12.1](https://github.com/laravel/laravel/compare/v12.12.0...v12.12.1) - 2026-03-10

* [12.x] Makes imports consistent by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6760

## [v12.12.0](https://github.com/laravel/laravel/compare/v12.11.2...v12.12.0) - 2026-03-09

* Update phpunit version to ^11.5.50 to address CVE by [@PerryvanderMeer](https://github.com/PerryvanderMeer) in https://github.com/laravel/laravel/pull/6746
* [12.x] Add `APP_NAME` fallback in mail config by [@apoorvdarshan](https://github.com/apoorvdarshan) in https://github.com/laravel/laravel/pull/6755
* [12.x] Neutralize DB_URL in default phpunit.xml by [@Husseinadq](https://github.com/Husseinadq) in https://github.com/laravel/laravel/pull/6761

## [v12.11.2](https://github.com/laravel/laravel/compare/v12.11.1...v12.11.2) - 2026-01-19

* [12.x] Update composer dev script to ensure no timeout by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6735
* [12.x] Update jobs/cache migrations by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6736
* [12.x] Remove failed jobs indexes by [@jackbayliss](https://github.com/jackbayliss) in https://github.com/laravel/laravel/pull/6739
* [12.x] Add `APP_URL` fallback in filesystems config by [@KentarouTakeda](https://github.com/KentarouTakeda) in https://github.com/laravel/laravel/pull/6742
* chore: Update outdated GitHub Actions version by [@pgoslatara](https://github.com/pgoslatara) in https://github.com/laravel/laravel/pull/6743

## [v12.11.1](https://github.com/laravel/laravel/compare/v12.11.0...v12.11.1) - 2025-12-23

* Use environment variable for `DB_SSLMODE` - Postgres by [@robsontenorio](https://github.com/robsontenorio) in https://github.com/laravel/laravel/pull/6727
* fix: ensure APP_URL does not have trailing slash in filesystem by [@msamgan](https://github.com/msamgan) in https://github.com/laravel/laravel/pull/6728

## [v12.11.0](https://github.com/laravel/laravel/compare/v12.10.1...v12.11.0) - 2025-11-25

* fix: cookies are not available for subdomains by default by [@joostdebruijn](https://github.com/joostdebruijn) in https://github.com/laravel/laravel/pull/6705
* Fix PHP 8.5 PDO Driver Specific Constant Deprecation by [@RyanSchaefer](https://github.com/RyanSchaefer) in https://github.com/laravel/laravel/pull/6710
* Ignore Laravel compiled views for Vite  by [@QistiAmal1212](https://github.com/QistiAmal1212) in https://github.com/laravel/laravel/pull/6714

## [v12.10.1](https://github.com/laravel/laravel/compare/v12.10.0...v12.10.1) - 2025-11-06

* Update schema URL in package.json by [@robinmiau](https://github.com/robinmiau) in https://github.com/laravel/laravel/pull/6701

## [v12.10.0](https://github.com/laravel/laravel/compare/v12.9.1...v12.10.0) - 2025-11-04

* Add background driver by [@barryvdh](https://github.com/barryvdh) in https://github.com/laravel/laravel/pull/6699

## [v12.9.1](https://github.com/laravel/laravel/compare/v12.9.0...v12.9.1) - 2025-10-23

* [12.x] Replace Bootcamp with Laravel Learn by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6692
* [12.x] Comment out CLI workers for fresh applications by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/laravel/pull/6693

## [v12.9.0](https://github.com/laravel/laravel/compare/v12.8.0...v12.9.0) - 2025-10-21

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.8.0...v12.9.0

## [v12.8.0](https://github.com/laravel/laravel/compare/v12.7.1...v12.8.0) - 2025-10-20

* [12.x] Makes test suite using broadcast's `null` driver by [@nunomaduro](https://github.com/nunomaduro) in https://github.com/laravel/laravel/pull/6691

## [v12.7.1](https://github.com/laravel/laravel/compare/v12.7.0...v12.7.1) - 2025-10-15

* Added `failover` driver to the `queue` config comment.  by [@sajjadhossainshohag](https://github.com/sajjadhossainshohag) in https://github.com/laravel/laravel/pull/6688

## [v12.7.0](https://github.com/laravel/laravel/compare/v12.6.0...v12.7.0) - 2025-10-14

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.6.0...v12.7.0

## [v12.6.0](https://github.com/laravel/laravel/compare/v12.5.0...v12.6.0) - 2025-10-02

* Fix setup script by [@goldmont](https://github.com/goldmont) in https://github.com/laravel/laravel/pull/6682

## [v12.5.0](https://github.com/laravel/laravel/compare/v12.4.0...v12.5.0) - 2025-09-30

* [12.x] Fix type casting for environment variables in config files by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6670
* Fix CVEs affecting vite by [@faissaloux](https://github.com/faissaloux) in https://github.com/laravel/laravel/pull/6672
* Update .editorconfig to target compose.yaml by [@fredikaputra](https://github.com/fredikaputra) in https://github.com/laravel/laravel/pull/6679
* Add pre-package-uninstall script to composer.json by [@cosmastech](https://github.com/cosmastech) in https://github.com/laravel/laravel/pull/6681

## [v12.4.0](https://github.com/laravel/laravel/compare/v12.3.1...v12.4.0) - 2025-08-29

* [12.x] Add default Redis retry configuration by [@mateusjatenee](https://github.com/mateusjatenee) in https://github.com/laravel/laravel/pull/6666

## [v12.3.1](https://github.com/laravel/laravel/compare/v12.3.0...v12.3.1) - 2025-08-21

* [12.x] Bump Pint version by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6653
* [12.x] Making sure all related processed are closed when terminating the currently command by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6654
* [12.x] Use application name from configuration by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6655
* Bring back postAutoloadDump script by [@jasonvarga](https://github.com/jasonvarga) in https://github.com/laravel/laravel/pull/6662

## [v12.3.0](https://github.com/laravel/laravel/compare/v12.2.0...v12.3.0) - 2025-08-03

* Fix Critical Security Vulnerability in form-data Dependency by [@izzygld](https://github.com/izzygld) in https://github.com/laravel/laravel/pull/6645
* Revert "fix" by [@RobertBoes](https://github.com/RobertBoes) in https://github.com/laravel/laravel/pull/6646
* Change composer post-autoload-dump script to Artisan command by [@lmjhs](https://github.com/lmjhs) in https://github.com/laravel/laravel/pull/6647

## [v12.2.0](https://github.com/laravel/laravel/compare/v12.1.0...v12.2.0) - 2025-07-11

* Add Vite 7 support by [@timacdonald](https://github.com/timacdonald) in https://github.com/laravel/laravel/pull/6639

## [v12.1.0](https://github.com/laravel/laravel/compare/v12.0.11...v12.1.0) - 2025-07-03

* [12.x] Disable nightwatch in testing by [@laserhybiz](https://github.com/laserhybiz) in https://github.com/laravel/laravel/pull/6632
* [12.x] Reorder environment variables in phpunit.xml for logical grouping by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6634
* Change to hyphenate prefixes and cookie names by [@u01jmg3](https://github.com/u01jmg3) in https://github.com/laravel/laravel/pull/6636
* [12.x] Fix type casting for environment variables in config files by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6637

## [v12.0.11](https://github.com/laravel/laravel/compare/v12.0.10...v12.0.11) - 2025-06-10

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.0.10...v12.0.11

## [v12.0.10](https://github.com/laravel/laravel/compare/v12.0.9...v12.0.10) - 2025-06-09

* fix alphabetical order by [@Khuthaily](https://github.com/Khuthaily) in https://github.com/laravel/laravel/pull/6627
* [12.x] Reduce redundancy and keeps the .gitignore file cleaner by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6629
* [12.x] Fix: Add void return type to satisfy Rector analysis by [@Aluisio-Pires](https://github.com/Aluisio-Pires) in https://github.com/laravel/laravel/pull/6628

## [v12.0.9](https://github.com/laravel/laravel/compare/v12.0.8...v12.0.9) - 2025-05-26

* [12.x] Remove apc by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6611
* [12.x] Add JSON Schema to package.json by [@martinbean](https://github.com/martinbean) in https://github.com/laravel/laravel/pull/6613
* Minor language update by [@woganmay](https://github.com/woganmay) in https://github.com/laravel/laravel/pull/6615
* Enhance .gitignore to exclude common OS and log files by [@mohammadRezaei1380](https://github.com/mohammadRezaei1380) in https://github.com/laravel/laravel/pull/6619

## [v12.0.8](https://github.com/laravel/laravel/compare/v12.0.7...v12.0.8) - 2025-05-12

* [12.x] Clean up URL formatting in README by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6601

## [v12.0.7](https://github.com/laravel/laravel/compare/v12.0.6...v12.0.7) - 2025-04-15

* Add `composer run test` command by [@crynobone](https://github.com/crynobone) in https://github.com/laravel/laravel/pull/6598
* Partner Directory Changes in ReadME by [@joshcirre](https://github.com/joshcirre) in https://github.com/laravel/laravel/pull/6599

## [v12.0.6](https://github.com/laravel/laravel/compare/v12.0.5...v12.0.6) - 2025-04-08

**Full Changelog**: https://github.com/laravel/laravel/compare/v12.0.5...v12.0.6

## [v12.0.5](https://github.com/laravel/laravel/compare/v12.0.4...v12.0.5) - 2025-04-02

* [12.x] Update `config/mail.php` to match the latest core configuration by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6594

## [v12.0.4](https://github.com/laravel/laravel/compare/v12.0.3...v12.0.4) - 2025-03-31

* Bump vite from 6.0.11 to 6.2.3 - Vulnerability patch by [@abdel-aouby](https://github.com/abdel-aouby) in https://github.com/laravel/laravel/pull/6586
* Bump vite from 6.2.3 to 6.2.4 by [@thinkverse](https://github.com/thinkverse) in https://github.com/laravel/laravel/pull/6590

## [v12.0.3](https://github.com/laravel/laravel/compare/v12.0.2...v12.0.3) - 2025-03-17

* Remove reverted change from CHANGELOG.md by [@AJenbo](https://github.com/AJenbo) in https://github.com/laravel/laravel/pull/6565
* Improves clarity in app.css file by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6569
* [12.x] Refactor: Structural improvement for clarity by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6574
* Bump axios from 1.7.9 to 1.8.2 - Vulnerability patch by [@abdel-aouby](https://github.com/abdel-aouby) in https://github.com/laravel/laravel/pull/6572
* [12.x] Remove Unnecessarily [@source](https://github.com/source) by [@AhmedAlaa4611](https://github.com/AhmedAlaa4611) in https://github.com/laravel/laravel/pull/6584

## [v12.0.2](https://github.com/laravel/laravel/compare/v12.0.1...v12.0.2) - 2025-03-04

* Make the github test action run out of the box independent of the choice of testing framework by [@ndeblauw](https://github.com/ndeblauw) in https://github.com/laravel/laravel/pull/6555

## [v12.0.1](https://github.com/laravel/laravel/compare/v12.0.0...v12.0.1) - 2025-02-24

* [12.x] prefer stable stability by [@pataar](https://github.com/pataar) in https://github.com/laravel/laravel/pull/6548

## [v12.0.0 (2025-??-??)](https://github.com/laravel/laravel/compare/v11.0.2...v12.0.0)

Laravel 12 includes a variety of changes to the application skeleton. Please consult the diff to see what's new.
