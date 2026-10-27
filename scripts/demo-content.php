<?php

/**
 * @file
 * Tweaks applied around Devel Generate, run via: drush php:script.
 *
 * Usage: drush php:script scripts/demo-content.php -- before|after
 */

use Drupal\layout_builder\SectionComponent;
use Drupal\user\Entity\Role;

$step = $extra[0] ?? '';
$etm = \Drupal::entityTypeManager();

if ($step === 'before') {
  // Umami renders articles with Layout Builder, so the comment field added by
  // the core "article_comment" recipe must also be placed as a layout block.
  $display = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article', 'full');
  $sections = $display->getSections();
  $section = end($sections);
  $section->appendComponent(new SectionComponent(\Drupal::service('uuid')->generate(), 'content', [
    'id' => 'field_block:node:article:comment',
    'label' => 'Comments',
    'label_display' => '0',
    'provider' => 'layout_builder',
    'formatter' => [
      'label' => 'above',
      'type' => 'comment_default',
      'settings' => ['view_mode' => 'default', 'pager_id' => 0],
      'third_party_settings' => [],
    ],
    'context_mapping' => ['entity' => 'layout_builder.entity', 'view_mode' => 'view_mode'],
  ]));
  $display->save();

  $anonymous = Role::load('anonymous');
  $anonymous->grantPermission('access comments');
  $anonymous->save();
  echo "Comments enabled on Umami article layout.\n";
}

if ($step === 'after') {
  // Devel Generate fills the per-node Layout Builder override field with random
  // data, which would replace the normal article/page design. Clear it.
  $storage = $etm->getStorage('node');
  foreach ($storage->loadMultiple() as $node) {
    if ($node->hasField('layout_builder__layout') && !$node->get('layout_builder__layout')->isEmpty()) {
      $node->set('layout_builder__layout', []);
      $node->save();
    }
  }

  // Devel Generate creates a mix of unpublished comments; publish them all.
  foreach ($etm->getStorage('comment')->loadMultiple() as $comment) {
    if (!$comment->isPublished()) {
      $comment->setPublished()->save();
    }
  }
  echo "Generated content cleaned up.\n";
}
