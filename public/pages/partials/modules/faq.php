<?php
/**
 * FAQ Accordion module partial
 *
 * Caller sets $module before including:
 * $module = [
 *   'id'          => 'pricing-faq',           // unique section id (required)
 *   'modifier'    => 'faq--dark',             // BEM modifier class (optional)
 *   'heading'     => 'Common questions',       // section heading (required)
 *   'heading_tag' => 'h2',                     // h2 by default (optional)
 *   'items'       => [
 *     ['q' => 'Question?', 'a' => 'Answer.'],
 *   ],
 * ];
 *
 * Base CSS lives in global.css under the MODULE STYLES section.
 * JS initialiser lives in site.js under MODULE INITIALISERS.
 */
$mod     = $module['modifier']    ?? '';
$headId  = ($module['id'] ?? 'faq') . '-heading';
$tag     = $module['heading_tag'] ?? 'h2';
$sectionId = htmlspecialchars($module['id'] ?? 'faq', ENT_QUOTES, 'UTF-8');
$modClass  = $mod ? ' ' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') : '';
?>
<section id="<?= $sectionId ?>" class="faq<?= $modClass ?>" aria-labelledby="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>">
  <div class="faq__inner">
    <<?= $tag ?> id="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>" class="faq__heading"><?= htmlspecialchars($module['heading'], ENT_QUOTES, 'UTF-8') ?></<?= $tag ?>>
    <dl class="faq__list">
      <?php foreach ($module['items'] as $i => $item): ?>
      <div class="faq__item" data-faq-item>
        <dt class="faq__question">
          <button class="faq__toggle" aria-expanded="false" aria-controls="faq-answer-<?= $sectionId ?>-<?= (int)$i ?>">
            <?= htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8') ?>
            <span class="faq__icon" aria-hidden="true"></span>
          </button>
        </dt>
        <dd class="faq__answer" id="faq-answer-<?= $sectionId ?>-<?= (int)$i ?>" hidden>
          <div class="faq__answer-inner"><?= nl2br(htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8')) ?></div>
        </dd>
      </div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
