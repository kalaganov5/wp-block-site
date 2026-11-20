// Исходные данные сайта до запуска: тексты, страницы, услуги, подписи интерфейса, цены, меню, редиректы.
// Из них tools/build-content.js собирает content.json (разметка блоков Gutenberg) и подписи темы.
// Если есть прототип (HTML/JS из дизайн-инструмента) — сборщик читает его, а этот файл заменяется.
// После запуска сайта тексты правятся в админке, а не здесь.
module.exports = {
  version: 1, // увеличить, если поменялись адреса или структура: тестовый сайт перезальёт контент сам

  // settings — начальные значения «Настройки → Acme Site» (контакты, регион работы, адрес): импорт ставит их
  // только на новом сайте, сохранённое владельцем не перезаписывает.
  site: {
    name: 'Acme Site',
    description: 'Короткое описание сайта: кто вы и что делаете',
    settings: { area_served: 'Россия' },
  },

  // Первый язык — основной (адреса без префикса). Flag — код флага Polylang.
  languages: [
    { slug: 'ru', name: 'Русский', locale: 'ru_RU', flag: 'ru' },
    { slug: 'en', name: 'English', locale: 'en_US', flag: 'us' },
  ],

  // Цены как переменные: в тексте {{price:hour}}, значения — «Настройки → Цены».
  prices: {
    hour: { label: 'Час работы', ru: '2 000 ₽', en: '$25' },
  },

  topics: [{ key: 'general', order: 1, names: { ru: 'Общие задачи', en: 'General' } }],

  // Подписи интерфейса на всех языках — в соседнем файле strings.js.
  strings: require('./strings'),

  // Страницы. role — служебная роль (код находит страницу по роли, а не по адресу).
  // Разные языки — разные слаги: бесплатный Polylang не даёт одинаковые адреса.
  // title — он же заголовок H1 страницы (его выводит тема): в тексте записи H1 быть не должно.
  // SEO-поля обязательны на каждом языке, сборщик проверяет их (tools/lib/seo-rules.js):
  //   seoTitle — заголовок в выдаче, 10–60 знаков; seoDescription — описание, 70–160 знаков;
  //   focusKeyword — необязательный главный запрос страницы (подсказки Rank Math в редакторе).
  //   Заголовки и описания не повторяются внутри языка; метки {{price:ключ}} в них работают.
  pages: [
    {
      key: 'home',
      role: 'home',
      slug: { ru: 'main', en: 'home' },
      title: { ru: 'Acme Site — заголовок первого экрана', en: 'Acme Site — hero heading' },
      seoTitle: {
        ru: 'Acme Site — услуги для малого бизнеса под ключ',
        en: 'Acme Site — turnkey services for small business',
      },
      seoDescription: {
        ru: 'Acme Site помогает малому бизнесу: понятные услуги, точные сроки и цена от {{price:hour}} за час. Оставьте заявку — ответим в течение рабочего дня.',
        en: 'Acme Site helps small businesses: clear services, firm deadlines and rates from {{price:hour}} per hour. Send a request and we reply within one business day.',
      },
      hero: {
        ru: {
          lead: 'Первый абзац сразу отвечает на главный вопрос посетителя. Час работы — {{price:hour}}.',
          cta: 'Оставить заявку',
        },
        en: {
          lead: 'The first paragraph answers the visitor’s main question right away. One hour of work — {{price:hour}}.',
          cta: 'Send a request',
        },
      },
      cards: { ru: 'С чем приходят', en: 'What people come with' },
    },
    {
      key: 'about',
      role: 'about',
      slug: { ru: 'about', en: 'about-us' },
      title: { ru: 'О нас', en: 'About us' },
      seoTitle: {
        ru: 'О компании Acme Site: кто мы и как работаем',
        en: 'About Acme Site: who we are and how we work',
      },
      seoDescription: {
        ru: 'Кто стоит за Acme Site: сколько лет работаем, с какими задачами приходят клиенты и чем мы отличаемся от других подрядчиков.',
        en: 'Who is behind Acme Site: how long we have been in business, what clients come to us with and what sets us apart from other contractors.',
      },
      text: {
        ru: ['Кто мы, сколько лет работаем, чем отличаемся.'],
        en: ['Who we are, how long we have worked, what makes us different.'],
      },
    },
    {
      key: 'contacts',
      role: 'contacts',
      slug: { ru: 'contacts', en: 'contact' },
      title: { ru: 'Контакты', en: 'Contact' },
      seoTitle: {
        ru: 'Контакты Acme Site: как с нами связаться',
        en: 'Contact Acme Site: how to reach us',
      },
      seoDescription: {
        ru: 'Напишите в Acme Site удобным способом: Telegram, e-mail или форма на сайте. Отвечаем в рабочие дни в течение нескольких часов.',
        en: 'Reach Acme Site the way you prefer: Telegram, e-mail or the form on this page. We reply on business days within a few hours.',
      },
      text: {
        ru: ['Как с нами связаться и когда мы отвечаем.'],
        en: ['How to reach us and when we reply.'],
      },
      form: true,
    },
    {
      key: 'blog',
      role: 'blog',
      slug: { ru: 'blog', en: 'articles' },
      title: { ru: 'Блог', en: 'Articles' },
      seoTitle: {
        ru: 'Блог Acme Site: статьи и разборы из практики',
        en: 'Acme Site articles: guides from our practice',
      },
      seoDescription: {
        ru: 'Статьи Acme Site о нашей работе: разборы задач клиентов, пошаговые инструкции и ответы на вопросы, которые нам задают чаще всего.',
        en: 'Acme Site articles about our work: client case breakdowns, step-by-step guides and answers to the questions we hear most often.',
      },
      excerpt: { ru: 'Статьи о нашей работе.', en: 'Articles about our work.' },
    },
    {
      key: 'privacy',
      role: 'privacy',
      slug: { ru: 'privacy-policy', en: 'privacy' },
      title: { ru: 'Политика конфиденциальности', en: 'Privacy policy' },
      seoTitle: {
        ru: 'Политика конфиденциальности сайта Acme Site',
        en: 'Acme Site privacy policy',
      },
      seoDescription: {
        ru: 'Какие персональные данные собирает сайт Acme Site, зачем они нужны, сколько хранятся и как попросить их изменить или удалить.',
        en: 'What personal data the Acme Site website collects, why it is needed, how long it is kept and how to ask us to change or delete it.',
      },
      text: { ru: ['Текст политики.'], en: ['Policy text.'] },
    },
  ],

  // Услуги (тип acme_service). untranslated — текст пока только на основном языке: копия закрыта от индексации.
  services: [
    {
      key: 'example',
      topic: 'general',
      slug: { ru: 'example', en: 'example-service' },
      title: { ru: 'Пример услуги', en: 'Example service' },
      seoTitle: {
        ru: 'Пример услуги — цена, сроки и что входит',
        en: 'Example service — price, timing and scope',
      },
      seoDescription: {
        ru: 'Пример услуги от Acme Site: какую задачу решает, что входит в работу и сколько стоит — от {{price:hour}} за час. Оставьте заявку на странице.',
        en: 'Example service by Acme Site: what problem it solves, what the work includes and what it costs — from {{price:hour}} per hour. Send a request.',
      },
      focusKeyword: { ru: 'пример услуги', en: 'example service' },
      lead: {
        ru: 'Какую задачу решает услуга — одним-двумя предложениями.',
        en: 'What problem the service solves, in one or two sentences.',
      },
      facts: {
        ru: ['Что входит — пункт 1', 'Что входит — пункт 2'],
        en: ['What is included — item 1', 'What is included — item 2'],
      },
      faq: {
        ru: [['Сколько стоит?', 'От {{price:hour}} за час работы.']],
        en: [['How much does it cost?', 'From {{price:hour}} per hour.']],
      },
      meta: {
        acme_short: { ru: 'Пример', en: 'Example' },
        acme_price: { ru: 'от {{price:hour}}', en: 'from {{price:hour}}' },
      },
    },
  ],

  // Статьи блога (тип post). text — абзацы; excerpt — отрывок для карточки в списке.
  posts: [
    {
      key: 'first-post',
      slug: { ru: 'kak-my-rabotaem', en: 'how-we-work' },
      title: {
        ru: 'Как мы работаем: от заявки до результата',
        en: 'How we work: from request to result',
      },
      seoTitle: {
        ru: 'Как мы работаем: от заявки до результата — Acme Site',
        en: 'How we work: from request to result — Acme Site',
      },
      seoDescription: {
        ru: 'Этапы работы Acme Site: заявка, оценка задачи, согласование сроков и цены, выполнение и проверка результата вместе с клиентом.',
        en: 'How Acme Site works: request, task estimate, agreeing on timing and price, doing the job and checking the result together with the client.',
      },
      excerpt: {
        ru: 'Пример статьи: из каких шагов состоит работа и что получает клиент на каждом.',
        en: 'A sample article: the steps of our work and what the client gets at each one.',
      },
      text: {
        ru: [
          'Первый абзац статьи сразу отвечает на вопрос из заголовка: работа идёт в четыре шага, и на каждом клиент знает срок и цену.',
          'Дальше — подробности по каждому шагу. Замените этот текст своим: статьи после запуска пишутся в админке.',
        ],
        en: [
          'The first paragraph answers the question in the title right away: the work has four steps, and at each one the client knows the timing and the price.',
          'Then come the details of every step. Replace this text with your own: after launch articles are written in the admin.',
        ],
      },
    },
  ],

  // Меню по областям темы. Пункт: { role } | { key } | { archive } | { url }; label — ключ подписи.
  menus: {
    acme_header: [
      { archive: 'acme_service', label: 'navServices' },
      { role: 'about', label: 'navAbout' },
      { role: 'blog', label: 'navBlog' },
      { role: 'contacts', label: 'navContacts' },
    ],
    acme_footer: [
      { archive: 'acme_service', label: 'navServices' },
      { role: 'about', label: 'navAbout' },
      { role: 'blog', label: 'navBlog' },
      { role: 'contacts', label: 'navContacts' },
    ],
    acme_footer_docs: [{ role: 'privacy', label: 'privacyShort' }],
  },

  // Старые адреса → цель (ссылкой на запись, а не адресом). CI проверяет каждый (ci.yml, блок MAP).
  redirects: {
    '/old-services/': { archive: 'acme_service' },
  },
};
