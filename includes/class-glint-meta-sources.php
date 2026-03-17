<?php

class Glint_AI_SEO_Meta_Sources
{

    public function get_core_metas()
    {
        return array(
            'post_title' => 'Post Title',
            'post_excerpt' => 'Post Excerpt',
            'post_name' => 'Post Slug',
            'post_date' => 'Post Date',
            'post_author' => 'Author ID',
            '_thumbnail_id' => 'Featured Image ID (Thumbnail)'
        );
    }

    public function get_acf_metas()
    {
        $metas = array();
        if (function_exists('acf_get_field_groups')) {
            $all_post_types = get_post_types(array('public' => true), 'names');
            foreach ($all_post_types as $pt) {
                $metas[$pt] = array();
                $groups = acf_get_field_groups(array('post_type' => $pt));
                if ($groups) {
                    foreach ($groups as $group) {
                        $fields = acf_get_fields($group['key']);
                        if ($fields) {
                            foreach ($fields as $field) {
                                $metas[$pt][$field['name']] = $field['label'] . ' (' . $field['name'] . ')';
                            }
                        }
                    }
                }
            }
        }
        return $metas;
    }

    public function get_woo_metas()
    {
        // Common standard woo product metas
        $metas = array(
            '_price' => 'Price',
            '_regular_price' => 'Regular Price',
            '_sale_price' => 'Sale Price',
            '_sku' => 'SKU',
            '_stock_status' => 'Stock Status',
            '_weight' => 'Weight',
            '_length' => 'Length',
            '_width' => 'Width',
            '_height' => 'Height',
            'total_sales' => 'Total Sales'
        );

        // Add product attribute taxonomies (e.g. pa_color, pa_size)
        if (function_exists('wc_get_attribute_taxonomies')) {
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            if ($attribute_taxonomies) {
                foreach ($attribute_taxonomies as $attribute) {
                    $taxonomy_name = wc_attribute_taxonomy_name($attribute->attribute_name);
                    $metas[$taxonomy_name] = 'Attribute: ' . $attribute->attribute_label . ' (' . $taxonomy_name . ')';
                }
            }
        }

        return $metas;
    }

    public function get_taxonomy_sources()
    {
        $sources = array();
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        if ($taxonomies) {
            foreach ($taxonomies as $taxonomy) {
                $terms = array('0' => '-'); // Root option: get all items
                $parent_terms = get_terms(array(
                    'taxonomy' => $taxonomy->name,
                    'parent' => 0,
                    'hide_empty' => false,
                ));
                if (!is_wp_error($parent_terms) && !empty($parent_terms)) {
                    foreach ($parent_terms as $term) {
                        $terms[strval($term->term_id)] = $term->name;
                    }
                }
                $sources[$taxonomy->name] = array(
                    'label' => $taxonomy->label,
                    'terms' => $terms,
                );
            }
        }
        return $sources;
    }

}
