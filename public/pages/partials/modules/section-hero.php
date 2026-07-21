<?php
/**
 * Section Hero module partial
 *
 * Caller sets $module before including:
 * $module = [
 *   'id'           => 'hero',                         // unique section id (required)
 *   'modifier'     => 'section-hero--light',           // BEM modifier class (optional)
 *   'badge'        => 'New',                           // small pill above heading (optional)
 *   'heading'      => 'Page heading',                  // h1 text (required)
 *   'heading_tag'  => 'h1',                            // h1 by default (optional)
 *   'subtext'      => 'Supporting copy here.',         // paragraph below heading (optional)
 *   'cta_primary'  => ['text' => 'Get started', 'href' => '/register'],   // primary CTA (optional)
 *   'cta_secondary'=> ['text' => 'Learn more',  'href' => '#steps'],      // secondary CTA (optional)
 * ];
 *
 * Base CSS lives in global.css under the MODULE STYLES section.
 */
$sectionId = htmlspecialchars($module['id'] ?? 'hero', ENT_QUOTES, 'UTF-8');
$mod       = $module['modifier'] ?? '';
$modClass  = $mod ? ' ' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') : '';
$tag       = $module['heading_tag'] ?? 'h1';
$headId    = $sectionId . '-heading';
?>
<section id="<?= $sectionId ?>" class="section-hero<?= $modClass ?>" aria-labelledby="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>">
  <div class="section-hero__inner">
    <?php if (!empty($module['badge'])): ?>
    <span class="section-hero__badge"><?= htmlspecialchars($module['badge'], ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
    <<?= $tag ?> id="<?= htmlspecialchars($headId, ENT_QUOTES, 'UTF-8') ?>" class="section-hero__heading"><?= htmlspecialchars($module['heading'], ENT_QUOTES, 'UTF-8') ?></<?= $tag ?>>
    <?php if (!empty($module['subtext'])): ?>
    <p class="section-hero__subtext"><?= htmlspecialchars($module['subtext'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($module['cta_primary']) || !empty($module['cta_secondary'])): ?>
    <div class="section-hero__actions">
      <?php if (!empty($module['cta_primary'])): ?>
      <a href="<?= htmlspecialchars($module['cta_primary']['href'], ENT_QUOTES, 'UTF-8') ?>" class="section-hero__btn section-hero__btn--primary"><?= htmlspecialchars($module['cta_primary']['text'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php endif; ?>
      <?php if (!empty($module['cta_secondary'])): ?>
      <a href="<?= htmlspecialchars($module['cta_secondary']['href'], ENT_QUOTES, 'UTF-8') ?>" class="section-hero__btn section-hero__btn--secondary"><?= htmlspecialchars($module['cta_secondary']['text'], ENT_QUOTES, 'UTF-8') ?></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
