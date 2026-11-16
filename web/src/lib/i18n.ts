export const locales = ["ar", "en"] as const;

export type Locale = (typeof locales)[number];

export const defaultLocale: Locale = "ar";

export function isLocale(value: string): value is Locale {
  return locales.includes(value as Locale);
}

export function localeDir(locale: Locale): "rtl" | "ltr" {
  return locale === "ar" ? "rtl" : "ltr";
}

export function localePath(locale: Locale, path = ""): string {
  const suffix = path.startsWith("/") ? path : path ? `/${path}` : "";

  return `/${locale}${suffix}`;
}

const ar = {
  meta: {
    title: "عَقدار | منصة الخروج وإعادة البيع العقاري في مصر",
    description:
      "عَقدار تطبيق عربي أولاً لشراء وبيع وحدات إعادة البيع والتصفيات في مصر بالجنيه المصري. فريق المبيعات يدير التواصل، والقوائم تُراجع قبل النشر.",
  },
  nav: {
    what: "ما هي عَقدار",
    advantages: "المميزات",
    journey: "كيف تعمل",
    download: "حمّل التطبيق",
    privacy: "الخصوصية",
    language: "English",
    languageAria: "التبديل إلى الإنجليزية",
    menu: "القائمة",
    close: "إغلاق",
    skip: "تخطى إلى المحتوى",
  },
  hero: {
    kicker: "مصر · بالجنيه المصري",
    title: "منصة الخروج وإعادة البيع العقاري",
    lead: "حساب واحد تشتري منه وتبيع. عَقدار تعرض الوحدات بعد المراجعة، وفريق المبيعات يتولى كل تواصل حتى تبقى أرقامكم خاصة.",
    primary: "حمّل التطبيق",
    secondary: "تعرّف على عَقدار",
    qrHint: "امسح للتحميل",
    trust: ["عربي أولاً", "قوائم مراجعة", "تواصل عبر عَقدار", "مصر والجنيه فقط"],
  },
  cities: {
    label: "وحدات في أنحاء مصر",
    items: [
      "القاهرة",
      "الجيزة",
      "الإسكندرية",
      "العاصمة الإدارية",
      "الشيخ زايد",
      "التجمع الخامس",
      "السادس من أكتوبر",
      "الساحل الشمالي",
    ],
  },
  what: {
    kicker: "ما هي عَقدار",
    title: "سوق عقاري للخروج الآمن وإعادة البيع",
    lead: "عَقدار (AQDAR) تطبيق سوق عقاري مصري للوحدات المعاد بيعها ولمن يحتاج الخروج من وحدة قيد السداد. ليست بوابة إعلانات مفتوحة، وليست محادثة مباشرة بين المشتري والبائع.",
    pillars: [
      {
        title: "خروج",
        body: "إذا كنت بحاجة للخروج من وحدة أقساط، يساعدك التطبيق على عرضها بمسار واضح وتقدير أمين — دون اختراع أرقام.",
      },
      {
        title: "إعادة بيع",
        body: "تصفّح وحدات منشورة بعد المراجعة، احفظها، واطلب المعاينة. العملة جنيه مصري فقط.",
      },
      {
        title: "وساطة واثقة",
        body: "لا يظهر هاتفك أو بريدك للطرف الآخر. كل اهتمام يذهب إلى فريق مبيعات عَقدار.",
      },
    ],
    note: "حساب واحد يكفي. «اعرض عقارك» لا ينشئ حساباً ثانياً — تشتري وتبيع من نفس الملف.",
  },
  markets: {
    kicker: "سوقان واضحان",
    title: "إعادة بيع، أو تصفيات — كل مسار له قواعده",
    lead: "التصنيف واضح من أول شاشة حتى لا تختلط الوحدات العادية بفرص التصفيات.",
    items: [
      {
        tag: "السوق العام",
        title: "إعادة البيع",
        body: "وحدات يعرضها مالكها للبيع عبر المنصة بعد المراجعة. مناسبة لمن يبحث عن شقة أو فيلا أو أرض في السوق الثانوي.",
      },
      {
        tag: "سوق متخصص",
        title: "التصفيات",
        body: "فرص لوحدات تأخر سدادها أو يُتوقع تعثرها. المنصة تفصل هذا السوق حتى يعرف المشتري طبيعة الفرصة قبل أن يهتم.",
      },
    ],
  },
  advantages: {
    kicker: "لماذا عَقدار",
    title: "مميزات بُنيت لحماية الطرفين",
    lead: "كل ميزة هنا موجودة في المنتج اليوم: من الخصوصية حتى تقدير الخروج ورحلة البيع.",
    items: [
      {
        title: "خصوصية غير قابلة للتفاوض",
        body: "لا يظهر اسم البائع أو هاتفه أو بريده في القوائم العامة. لا دردشة بين المشتري والبائع داخل التطبيق.",
      },
      {
        title: "نشر بعد المراجعة",
        body: "القائمة تمر بمسودة ثم مراجعة الفريق. ما تراه منشوراً اجتاز المراجعة — الموافقة والنشر صلاحيتان منفصلتان.",
      },
      {
        title: "بحث وخريطة",
        body: "تصفح بالقائمة أو على الخريطة، مع المفضلة والمشاهدات الأخيرة وعمليات البحث المحفوظة.",
      },
      {
        title: "معالج بيع واضح",
        body: "خطوات متتابعة للتفاصيل والصور والمستندات، ثم إرسال للمراجعة وإعادة الإرسال إن طُلب تعديل.",
      },
      {
        title: "تقدير خروج أمين",
        body: "إن نقصت نسبة فسخ العقد لا يخترع التطبيق رقماً. يظهر أن التقدير غير قابل للحساب مع تنويه واضح.",
      },
      {
        title: "اهتمام يتحول إلى ملف",
        body: "«مهتم بالوحدة» ينشئ طلباً برقم مرجعي، وفريق المبيعات هو من يرد ويتابع.",
      },
      {
        title: "معاينة بمواعيد",
        body: "احجز مشاهدة على مواعيد متاحة بدل التنسيق العشوائي بين الأطراف.",
      },
      {
        title: "طلب مخصص",
        body: "لم تجد الوحدة؟ أرسل طلب مشتري. يُحفظ كتذكرة حتى لو لم توجد نتائج مطابقة.",
      },
    ],
  },
  journey: {
    kicker: "الرحلة",
    title: "كيف تستخدم عَقدار خطوة بخطوة",
    lead: "نفس الحساب للطرفين. اختر مسارك لترى التسلسل.",
    buyer: "مشتري",
    seller: "بائع",
    buyerSteps: [
      {
        title: "تصفّح واحفظ",
        body: "ابحث بالفلاتر أو الخريطة، أضف للمفضلة، واحفظ بحثك لتصلك التنبيهات.",
      },
      {
        title: "أبدِ اهتمامك",
        body: "اضغط «مهتم بالوحدة». يصلك رقم مرجعي، ويظهر رقم فريق مبيعات عَقدار — لا رقم البائع.",
      },
      {
        title: "معاينة عبر المنصة",
        body: "احجز موعداً من المواعيد المتاحة. التنسيق يتم عبر عَقدار لا عبر رسائل خاصة.",
      },
      {
        title: "تابع حالة النقل",
        body: "إن تقدّم الملف ترى مرحلة آمنة ومفهومة، دون تفاصيل داخلية أو بيانات الطرف الآخر.",
      },
    ],
    sellerSteps: [
      {
        title: "اعرض عقارك",
        body: "من نفس حسابك ابدأ المعالج: البيانات، الصور العامة، والمستندات الخاصة.",
      },
      {
        title: "مراجعة الفريق",
        body: "بعد الإرسال تُراجع القائمة. إن لزم تعديل تعود إليك ثم تعيد الإرسال.",
      },
      {
        title: "النشر واستقبال الاهتمام",
        body: "بعد الموافقة والنشر تظهر الوحدة للمشترين. ترى الاهتمام دون هوية المشتري.",
      },
      {
        title: "عَقدار تكمل الملف",
        body: "المبيعات تجمع الأطراف حتى النقل. العمولة تُسجَّل داخلياً — لا بوابة دفع داخل التطبيق.",
      },
    ],
  },
  trust: {
    kicker: "الثقة",
    title: "ما الذي لا يحدث في عَقدار — وهذا مقصود",
    lead: "المنصة وُضعت لتكون وسيطاً منضبطاً، لا سوق رسائل مفتوحة.",
    items: [
      {
        title: "لا تواصل مباشر",
        body: "كل أزرار التواصل تعرض رقم مبيعات عَقدار من الإعدادات، لا رقم المستخدم.",
      },
      {
        title: "مستندات خاصة",
        body: "العقود والإيصالات وبطاقة الرقم القومي تبقى خاصة، بفتح مؤقت ومسجّل.",
      },
      {
        title: "صور بعد المراجعة",
        body: "صور الوحدة تظهر للعامة بعد المراجعة، وتُعالَج قبل النشر.",
      },
      {
        title: "دعم من عَقدار",
        body: "المحادثة داخل التطبيق مع فريق عَقدار، وليست بين المشتري والبائع.",
      },
    ],
  },
  download: {
    kicker: "التطبيق",
    title: "عَقدار على جوالك",
    lead: "امسح رمز الاستجابة أو حمّل من المتجر. التطبيق هو واجهة السوق — هذا الموقع يعرّفك عليه.",
    qrLabel: "رمز تحميل تطبيق عَقدار",
    qrCaption: "افتح كاميرا الجوال وامسح الرمز",
    storeOverline: "حمّل عبر",
    ios: "TestFlight",
    android: "Google Play",
    storesHint: "حمّل النسخة التجريبية على iPhone عبر TestFlight",
    fallback:
      "امسح الرمز أو افتح رابط TestFlight لتثبيت التطبيق على جهازك.",
  },
  get: {
    title: "تحميل تطبيق عَقدار",
    lead: "اختر متجرك، أو امسح الرمز من جهاز آخر.",
    redirecting: "جارٍ فتح المتجر…",
    desktop: "من الكمبيوتر: امسح الرمز بهاتفك.",
  },
  privacy: {
    title: "الخصوصية في عَقدار",
    lead: "ملخص واضح لما يراه المستخدم في السوق — دون لغة قانونية مطوّلة.",
    items: [
      {
        title: "القوائم العامة",
        body: "لا تتضمن هاتف البائع أو بريده أو معرّفه. ما تراه هو الوحدة والسعر والموقع المعتمد للعرض.",
      },
      {
        title: "الاهتمام والمعاينة",
        body: "الطلب يصل فريق المبيعات. الطرف الآخر لا يستلم رقمك من التطبيق.",
      },
      {
        title: "الملفات الخاصة",
        body: "مستندات إثبات الملكية والهوية ليست للمعرض العام، وفتحها للموظفين المختصين يُسجَّل.",
      },
      {
        title: "إغلاق الحساب",
        body: "يمكنك طلب إغلاق الحساب من التطبيق. يراجعه الفريق ثم يُزال التعريف عند التنفيذ.",
      },
    ],
  },
  footer: {
    blurb: "سوق خروج وإعادة بيع عقاري لمصر. عربي أولاً، والجنيه هو العملة الوحيدة.",
    product: "المنتج",
    legal: "قانوني",
    contact: "التواصل",
    sales: "مبيعات عَقدار",
    salesLead: "للاستفسار والمعاينة والتواصل بخصوص أي وحدة",
    call: "اتصل الآن",
    whatsapp: "واتساب",
    rights: "عَقدار. جميع الحقوق محفوظة.",
  },
  scrollTop: "العودة للأعلى",
  phone: {
    search: "ابحث في القاهرة أو الشيخ زايد",
    resale: "إعادة بيع",
    distressed: "تصفيات",
    map: "خريطة",
    listingOneTitle: "شقة 165م · التجمع الخامس",
    listingOneMeta: "3 غرف · جاهزة",
    listingOnePrice: "4,250,000 ج.م",
    listingTwoTitle: "فيلا · الشيخ زايد",
    listingTwoMeta: "إعادة بيع · حديقة",
    listingTwoPrice: "12,900,000 ج.م",
    home: "رئيسية",
    explore: "استكشف",
    sell: "اعرض",
    saved: "محفوظ",
    account: "حسابي",
  },
  notFound: {
    title: "هذه الصفحة غير موجودة",
    action: "العودة للرئيسية",
  },
};

const en: typeof ar = {
  meta: {
    title: "AQDAR | Egypt’s exit and resale property marketplace",
    description:
      "AQDAR is an Arabic-first marketplace for resale and exit properties in Egypt, priced in EGP. Listings are reviewed before they go public, and AQDAR Sales handles every conversation.",
  },
  nav: {
    what: "What is AQDAR",
    advantages: "Advantages",
    journey: "How it works",
    download: "Get the app",
    privacy: "Privacy",
    language: "العربية",
    languageAria: "Switch to Arabic",
    menu: "Menu",
    close: "Close",
    skip: "Skip to content",
  },
  hero: {
    kicker: "Egypt · EGP only",
    title: "The marketplace for property exit and resale",
    lead: "One account to buy and to sell. AQDAR publishes units only after review, and our sales team handles every conversation so phone numbers stay private.",
    primary: "Get the app",
    secondary: "See how AQDAR works",
    qrHint: "Scan to download",
    trust: ["Arabic first", "Reviewed listings", "Contact via AQDAR", "Egypt & EGP only"],
  },
  cities: {
    label: "Homes across Egypt",
    items: [
      "Cairo",
      "Giza",
      "Alexandria",
      "New Capital",
      "Sheikh Zayed",
      "New Cairo",
      "6th of October",
      "North Coast",
    ],
  },
  what: {
    kicker: "What is AQDAR",
    title: "A marketplace for a safe exit and a clean resale",
    lead: "AQDAR (عَقدار) is an Egyptian marketplace for resale units and for owners who need to exit an installment property. It is not an open classifieds board, and it is not a chat app between buyers and sellers.",
    pillars: [
      {
        title: "Exit",
        body: "If you need to leave an installment unit, the app gives you a guided listing path and an honest estimate — it will not invent a penalty figure.",
      },
      {
        title: "Resale",
        body: "Browse published units after staff review, save them, and request a viewing. Prices are EGP only.",
      },
      {
        title: "Trusted desk",
        body: "Your phone and email are never shown to the other party. Every “I’m interested” goes to AQDAR Sales.",
      },
    ],
    note: "One account is enough. “List your property” does not create a second login — you buy and sell from the same profile.",
  },
  markets: {
    kicker: "Two clear markets",
    title: "Resale, or distressed — each path has its own rules",
    lead: "The split is visible from the first screen so ordinary resale is never mixed with distressed opportunities.",
    items: [
      {
        tag: "Core market",
        title: "Resale",
        body: "Owners list units through the platform after review. Built for buyers looking at the secondary market — apartments, villas, and land.",
      },
      {
        tag: "Specialist market",
        title: "Distressed",
        body: "Opportunities where payments are already overdue, or default is expected. The product keeps this market separate so buyers know the nature of the unit before they enquire.",
      },
    ],
  },
  advantages: {
    kicker: "Why AQDAR",
    title: "Advantages designed to protect both sides",
    lead: "Every item below is in the product today — from privacy rules to exit estimates and the sell wizard.",
    items: [
      {
        title: "Privacy that does not bend",
        body: "Public listings never include the seller’s name, phone, or email. There is no in-app chat between buyer and seller.",
      },
      {
        title: "Published after review",
        body: "A listing moves from draft to staff review. What you see as published has been checked — approve and publish are separate permissions.",
      },
      {
        title: "Search and map",
        body: "Browse as a list or on the map, with favorites, recently viewed, and saved searches.",
      },
      {
        title: "A guided sell flow",
        body: "Step through details, photos, and private documents, then submit. If reviewers ask for changes, you resubmit from the same draft.",
      },
      {
        title: "Honest exit estimates",
        body: "If a contract cancellation rate is missing, the app says the estimate cannot be computed. It never fabricates a percentage.",
      },
      {
        title: "Interest becomes a case",
        body: "“Interested in this unit” opens a ticket with a reference number. AQDAR Sales is who replies.",
      },
      {
        title: "Viewings on a calendar",
        body: "Book from available slots instead of trading phone numbers to arrange a visit.",
      },
      {
        title: "Custom buyer request",
        body: "Cannot find the unit? Send a buyer request. It is saved as a ticket even when nothing matches yet.",
      },
    ],
  },
  journey: {
    kicker: "The journey",
    title: "How to use AQDAR, step by step",
    lead: "The same account works for both roles. Pick a path to see the sequence.",
    buyer: "Buyer",
    seller: "Seller",
    buyerSteps: [
      {
        title: "Browse and save",
        body: "Search with filters or the map, add favorites, and save a search so alerts can follow.",
      },
      {
        title: "Show interest",
        body: "Tap “interested”. You get a reference number and the AQDAR Sales contact — never the seller’s phone.",
      },
      {
        title: "View through the platform",
        body: "Book a slot from available times. Coordination stays inside AQDAR, not in private messages.",
      },
      {
        title: "Follow the transfer",
        body: "If a case proceeds, you see a safe public-facing stage — not internal codes or the other party’s details.",
      },
    ],
    sellerSteps: [
      {
        title: "List your property",
        body: "From the same account, start the wizard: facts, public photos, and private documents.",
      },
      {
        title: "Staff review",
        body: "After you submit, the team reviews. If changes are needed, the listing returns to you to resubmit.",
      },
      {
        title: "Go live and see interest",
        body: "Once approved and published, buyers can find the unit. You see interest without seeing who they are.",
      },
      {
        title: "AQDAR completes the file",
        body: "Sales brings both sides through transfer. Commission is recorded internally — there is no in-app payment gateway.",
      },
    ],
  },
  trust: {
    kicker: "Trust",
    title: "What AQDAR deliberately does not do",
    lead: "The product is a supervised desk, not an open messaging market.",
    items: [
      {
        title: "No direct contact",
        body: "Every contact action shows the AQDAR Sales number from settings, never a user’s number.",
      },
      {
        title: "Private documents stay private",
        body: "Contracts, receipts, and national ID files are not public. Access is short-lived and logged.",
      },
      {
        title: "Photos after review",
        body: "Unit photos become public only after review, and they are processed before publish.",
      },
      {
        title: "Support is AQDAR",
        body: "In-app messaging is with the AQDAR team, not between buyer and seller.",
      },
    ],
  },
  download: {
    kicker: "The app",
    title: "AQDAR on your phone",
    lead: "Scan the code or download from the store. The mobile app is the marketplace — this site is how you meet it.",
    qrLabel: "QR code to download the AQDAR app",
    qrCaption: "Open your camera and scan",
    storeOverline: "Install via",
    ios: "TestFlight",
    android: "Google Play",
    storesHint: "Install the iOS beta through TestFlight",
    fallback:
      "Scan the code or open the TestFlight link to install the app on your device.",
  },
  get: {
    title: "Download the AQDAR app",
    lead: "Choose your store, or scan the code from another device.",
    redirecting: "Opening the store…",
    desktop: "On a computer: scan the code with your phone.",
  },
  privacy: {
    title: "Privacy at AQDAR",
    lead: "A plain summary of what marketplace users actually see — not a legal essay.",
    items: [
      {
        title: "Public listings",
        body: "They do not include the seller’s phone, email, or user id. You see the unit, the price, and the location approved for display.",
      },
      {
        title: "Interest and viewings",
        body: "The request reaches the sales team. The other party does not receive your number from the app.",
      },
      {
        title: "Private files",
        body: "Ownership and identity documents are not for the public gallery. Staff access is purpose-bound and logged.",
      },
      {
        title: "Closing an account",
        body: "You can request account closure from the app. Staff review it, then anonymize when they complete the request.",
      },
    ],
  },
  footer: {
    blurb: "An exit and resale property marketplace for Egypt. Arabic first, EGP only.",
    product: "Product",
    legal: "Legal",
    contact: "Contact",
    sales: "AQDAR Sales",
    salesLead: "For inquiries, viewings, and any unit conversation",
    call: "Call now",
    whatsapp: "WhatsApp",
    rights: "AQDAR. All rights reserved.",
  },
  scrollTop: "Back to top",
  phone: {
    search: "Search Cairo or Sheikh Zayed",
    resale: "Resale",
    distressed: "Distressed",
    map: "Map",
    listingOneTitle: "165 m² apt · New Cairo",
    listingOneMeta: "3 beds · ready",
    listingOnePrice: "EGP 4,250,000",
    listingTwoTitle: "Villa · Sheikh Zayed",
    listingTwoMeta: "Resale · garden",
    listingTwoPrice: "EGP 12,900,000",
    home: "Home",
    explore: "Explore",
    sell: "Sell",
    saved: "Saved",
    account: "Account",
  },
  notFound: {
    title: "This page is not here",
    action: "Back to home",
  },
};

export type Dictionary = typeof ar;

export function getDictionary(locale: string): Dictionary {
  return locale === "en" ? en : ar;
}
