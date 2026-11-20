<?php
/**
 * Title: Страница услуги
 * Slug: acme/service-page
 * Categories: acme
 * Post Types: acme_service
 * Description: Все секции страницы услуги, как у остальных услуг. Замените текст в квадратных скобках.
 *
 * Заголовка H1 здесь нет намеренно: его выводит шаблон из поля «Заголовок» записи (core/post-title).
 * Содержимое начинается с лида, заголовки секций — H2.
 */
?>
<!-- wp:group {"tagName":"section","className":"acme-section"} -->
<section class="wp-block-group acme-section"><!-- wp:group {"className":"acme-wrap"} -->
<div class="wp-block-group acme-wrap"><!-- wp:paragraph {"className":"acme-lead"} -->
<p class="acme-lead">[Одно-два предложения: какую задачу решает услуга. Первый абзац — прямой ответ, его цитируют поисковики и ИИ.]</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-primary"} -->
<div class="wp-block-button is-style-primary"><a class="wp-block-button__link wp-element-button" href="#form">[Текст кнопки]</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"acme-section is-sunken"} -->
<section class="wp-block-group acme-section is-sunken"><!-- wp:group {"className":"acme-wrap"} -->
<div class="wp-block-group acme-wrap"><!-- wp:heading -->
<h2 class="wp-block-heading">Что входит</h2>
<!-- /wp:heading -->

<!-- wp:list {"className":"is-style-facts"} -->
<ul class="wp-block-list is-style-facts"><!-- wp:list-item -->
<li>[Пункт 1]</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>[Пункт 2]</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"acme-section"} -->
<section class="wp-block-group acme-section"><!-- wp:group {"className":"acme-wrap"} -->
<div class="wp-block-group acme-wrap"><!-- wp:heading -->
<h2 class="wp-block-heading">Как работаю</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true,"className":"is-style-steps"} -->
<ol class="wp-block-list is-style-steps"><!-- wp:list-item -->
<li>[Шаг 1]</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>[Шаг 2]</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"acme-section is-sunken"} -->
<section class="wp-block-group acme-section is-sunken"><!-- wp:group {"className":"acme-wrap acme-faq"} -->
<div class="wp-block-group acme-wrap acme-faq"><!-- wp:heading -->
<h2 class="wp-block-heading">Вопросы и ответы</h2>
<!-- /wp:heading -->

<!-- wp:details -->
<details class="wp-block-details"><summary>[Вопрос]</summary><!-- wp:paragraph -->
<p>[Короткий ответ по делу.]</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
