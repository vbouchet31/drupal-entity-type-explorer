<?php

/**
 * Usage:
 *  drush php:script entity_type_explorer --script-path=the/path/to/this/file.
 *
 * Output:
 *  The URL to the graph on GraphvizOnline visualizer.
 *
 * Configuration:
 *   $entity_types: array of entity types to explore. Ignored if empty.
 *   $light_colors: array of light colors for each entity type. #a6a6a6 by default.
 *   $colors: array of colors for each entity type. #808080 by default.
 *
 * Note:
 *   Only the "body", "name", "description", "info", 'comment' and "field_*" are
 *   displayed.
 *   Only the fields implementing EntityReferenceFieldItemListInterface or
 *   BlockFieldItemInterface are explored.
 */

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

$entity_types = [
  'block_content',
  'node',
  'media',
  'taxonomy_term',
  'paragraph',
  'comment'
];

$light_colors = [
  'block_content' => '#e5b699',
  'paragraph' => '#e9d1a9',
  'media' => '#9aacb5',
  'node' => '#fd9089',
  'taxonomy_term' => '#427ca2',
  'comment' => '#b8d8be',
];

$colors = [
  'block_content' => '#f5e2d6',
  'paragraph' => '#f9f2e6',
  'media' => '#c6d0d5',
  'node' => '#fed7d4',
  'taxonomy_term' => '#6da1c4',
  'comment' => '#e0f0e3',
];

$explored_field_names = [
  'name',
  'description',
  'info',
  'body',
  'comment',
];

$graph = 'digraph g {
fontname="Helvetica,Arial,sans-serif"
node [fontname="Helvetica,Arial,sans-serif"]
edge [fontname="Helvetica,Arial,sans-serif"]
graph [rankdir = "LR"];
node [fontsize = "16" shape = "ellipse"];
edge [];';
$links = '';
$index = 0;

$entity_definitions = \Drupal::entityTypeManager()->getDefinitions();

foreach ($entity_definitions as $definition_id => $definition) {
  if (!empty($entity_types) && !in_array($definition_id, $entity_types)) {
    continue;
  }

  $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($definition_id);
  foreach ($bundles as $bundle_id => $bundle_label) {
    $bundle_identifier = $definition_id . ':' . $bundle_id;

    $graph .= "\n" . '"' . $bundle_id . '" [label="<f0>' . $bundle_label['label'];

    if ($definition->entityClassImplements(FieldableEntityInterface::class)) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions($definition_id, $bundle_id);
      foreach ($fields as $field_id => $field) {
        $field_storage_definition = $field->getFieldStorageDefinition();
        $field_settings = $field->getItemDefinition()->getSettings();
        $is_reference = in_array('Drupal\Core\Field\EntityReferenceFieldItemListInterface', class_implements($field->getClass()));

        $field_name = $field_storage_definition->getName();

        // @TODO: Make it configurable.
        if (!in_array($field_name, $explored_field_names) && !str_starts_with($field_name, 'field_')) {
          continue;
        }

        $graph .= ' | <' . $field_name . '>' . $field->getLabel() . ' (' . $field_name . ')';

        if ($is_reference && $field_name !== $definition->getKey('bundle') && isset($field_settings['target_type']) && in_array($field_settings['target_type'], $entity_types)) {
          if (isset($field_settings['handler_settings']['target_bundles']) && !empty($field_settings['handler_settings']['target_bundles'])) {
            $target_bundles = $field_settings['handler_settings']['target_bundles'];

          }
          else {
            $target_bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo($field_settings['target_type']));
          }

          foreach ($target_bundles as $target_bundle) {
            $links .= "\n" . '"' . $bundle_id . '":' . $field_name . ' -> "' . $target_bundle . '":f0 [id=' . $index++ . '];';
          }
        }

        // Special case for "block_field" field type.
        // @See block_field module.
        // For now, we only handle the case where categories selection mode
        // is used and "block_content" is selected.
        if ($field->getType() === 'block_field' && $field_settings['selection'] === 'categories') {
          $categories = array_filter($field_settings['selection_settings']['categories']);
          if (in_array('Custom', $categories)) {
            foreach (array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('block_content')) as $target_bundle) {
              $links .= "\n" . '"' . $bundle_id . '":' . $field_name . ' -> "' . $target_bundle . '":f0 [id=' . $index++ . '];';
            }
          }
        }

        if ($field->getType() === 'comment') {
          if (isset($field_settings['comment_type']) && !empty($field_settings['comment_type'])) {
            $links .= "\n" . '"' . $bundle_id . '":' . $field_name . ' -> "' . $field_settings['comment_type'] . '":f0 [id=' . $index++ . '];';
          }
        }
      }
    }
    elseif ($definition instanceof ConfigEntityTypeInterface && $properties = $definition->getPropertiesToExport()) {
      foreach ($properties as $property) {
        if (!in_array($property, [
          '_core',
          'third_party_settings',
          'dependencies',
          'status'
        ])) {
          $graph .= ' | <' . $property . '>' . $property . ' (' . $property . ')';
        }
      }
    }

    $graph .= '" shape="record" group="' . $definition_id . '" style="filled" color="' . ($colors[$definition_id] ?? '#808080') . '" fillcolor="' . ($light_colors[$definition_id] ?? '#a6a6a6') . '" ];';
  }
}

$graph .= $links;
$graph .= "\n}";

echo 'https://dreampuf.github.io/GraphvizOnline/#' . rawurldecode($graph);
