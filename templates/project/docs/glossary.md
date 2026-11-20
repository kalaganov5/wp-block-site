# Словарь проекта

Одно понятие — одно английское имя во всём коде: в функциях, классах CSS, типах записей, ключах данных.
Перед тем как назвать новую сущность, найдите её здесь; если её нет — добавьте строку в том же коммите.

Зачем: без словаря одна сущность за месяц получает три имени (`request`, `order`, `lead`), и поиск по
проекту перестаёт находить всё, что к ней относится.

| Понятие | Имя в коде | Где встречается |
|---|---|---|
| Услуга | `service` | тип записи `acme_service`, блоки, `Service` в JSON-LD |
| Статья блога | `post` | стандартный тип WordPress |
| Заявка с формы | `lead` | `inc/leads.php`, тип записи заявок |
| Форма заявки | `lead form` | блок `acme/lead-form` |
| Подпись интерфейса (кнопка, пункт меню) | `string` | `acme_t()`, `strings` в `project/content/site.js` |
| Настройка сайта (контакты, профили) | `setting` | `acme_setting()`, опция `acme_settings` |
| Роль страницы («Цены», «Контакты») | `role` | `acme_role_url()`, опция `acme_roles` |
| Цена-переменная | `price` | `{{price:key}}`, `inc/prices.php` |
| Поле записи | `meta` | `acme_meta_schema()` |
| Хлебные крошки | `breadcrumbs` | блок `acme/breadcrumbs` |
| Перенаправление со старого адреса | `redirect` | `inc/redirects.php` |
| Закрыта от поисковиков | `closed` | `acme_seo_is_closed()` |
| Язык страницы | `lang` | `acme_lang()` |
| SEO-заголовок и SEO-описание записи | `seoTitle`, `seoDescription` | `project/content/site.js`, поля Rank Math `rank_math_title`, `rank_math_description` |
| Главный запрос страницы | `focusKeyword` | `project/content/site.js`, поле `rank_math_focus_keyword` |
| Подзаголовок страницы (первый абзац под H1) | `lead` | класс `.acme-lead`, `acme_seo_lead_text()` |
| Кто стоит за сайтом (эксперт или компания) | `entity` | `ACME_SEO_ENTITY`, узел Person/Organization в JSON-LD |
| Местная компания (адрес, часы работы) | `local business` | `inc/seo/local-business.php`, `LocalBusiness` в JSON-LD |
| Обложка сайта для соцсетей | `cover` | `content/covers/site-<lang>.jpg`, `inc/seo/social.php` |
| Код подтверждения вебмастера | `verification` | `inc/seo/verification.php`, настройки `verify_*` |
| Номер страницы списка («страница 2») | `page label` | `acme_seo_page_label()`, подпись `pageN` |
| Самопроверка SEO после деплоя | `status` | `wp acme seo`, `inc/seo/status.php` |
| SEO-гейт (проверка сайта в CI, блокирующие ошибки) | `gate` | `tools/qa/seo-gate.js` |
| Юридическая страница (политика, согласие) | `legal` | `acme_seo_legal_roles()` |

Булевы значения начинаются с `is_`, `has_`, `can_`: `is_closed`, `has_core`.
