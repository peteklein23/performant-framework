<?php

namespace PeteKlein\Performant\Menus;

use PeteKlein\Performant\Patterns\Singleton;

abstract class MenuBase extends Singleton
{
    /**
     * menu location to be overridden in inheriting class
     */
    const LOCATION = '';
    /**
     * theme slug to be overridden in the inheriting class
     */
    const THEME_SLUG = '';

    protected static $instances = [];

    /**
     * Registers the post type by calling Menu->register()
     */
    abstract public function register();

    public function __construct()
    {
        if (empty(static::LOCATION)) {
            throw new \Exception(__('You must set the constant LOCATION in your inheriting class', 'performant'));
        }

        if (empty(static::THEME_SLUG)) {
            throw new \Exception(__('You must set the constant THEME_SLUG in your inheriting class', 'performant'));
        }
    }

    /**
     * @inheritDoc
     *
     * @return MenuBase
     */
    public static function getInstance() : MenuBase
    {
        $cls = static::class;
        if (!isset(self::$instances[$cls])) {
            self::$instances[$cls] = new static;
        }

        return self::$instances[$cls];
    }

    /**
     * Register the menu
     *
     * @see https://codex.wordpress.org/Function_Reference/register_post_type#Arguments
     * @param array $args
     * @return void
     */
    protected function registerMenu($description) : void
    {
        $registeredMenu = register_nav_menu(static::LOCATION, $description);

        if (is_wp_error($registeredMenu)) {
            throw new \Exception('There was an issue registering the menu.');
        }
    }

    /**
     * Gets the id of the menu instance
     *
     * @return integer
     */
    public function getMenuId() : int
    {
        global $wpdb;

        $query = "SELECT
            option_value
        FROM $wpdb->options 
        WHERE option_name = 'theme_mods_" . static::THEME_SLUG . "'";
        
        $themeOptionsResult = $wpdb->get_var($query);
        if ($themeOptionsResult === false) {
            throw new \Exception('This there are no theme options registered for theme with "' . static::THEME_SLUG . '". Please make sure the theme is activated and the theme slug matches your directory.');
        }
        $themeOptions = maybe_unserialize($themeOptionsResult);

        $navMenuLocations = $themeOptions['nav_menu_locations'];
        if (empty($navMenuLocations[static::LOCATION])) {
            throw new \Exception('This theme has no menu options');
        }

        if (empty($navMenuLocations[static::LOCATION])) {
            throw new \Exception('There is no menu registered at the location ' . '"' . static::LOCATION . '." Please add a theme in the WordPress admin at that location to retrieve the ID.');
        }

        return (int) $navMenuLocations[static::LOCATION];
    }
    
    /**
     * nests the result under it's parent in the formattedResults
     *
     * @param array $formattedResults
     * @param integer $parentId
     * @param integer $resultId
     * @param array $result
     * @return void
     */
    private function insertNestedResult(array &$formattedResults, int $parentId, int $resultId, array $result) : void
    {
        if(empty($formattedResults)) {
            return;
        }

        foreach($formattedResults as $id => &$value) {
            if ($id === $parentId) {
                $value['children'][$resultId] = $result;
                return;
            }

            $this->insertNestedResult($value['children'], $parentId, $resultId, $result);
        }
    }

    /**
     * Adds a menu item to the formatted result
     *
     * @param array $formattedResults
     * @param [type] $result
     * @return void
     */
    private function insertMenuItem(array &$formattedResults, $result) : void
    {
        $formattedResult = [
            'ID' => (int) $result->ID,
            'parent_id' => (int) $result->parent_id,
            'post_title' => $result->post_title,
            'url' => !empty($result->url) ? $result->url : get_permalink($result->object_id),
            'children' => []
        ];
    
        if ( empty($result->parent_id) ) {
            $formattedResults[ $result->ID ] = $formattedResult;
            return;
        }

        $this->insertNestedResult($formattedResults, $result->parent_id, $result->ID, $formattedResult);
    }
    
    /**
     * Formats and nests the menu data from database results
     *
     * @param array $results
     * @return array
     */
    private function formatResults(array $results) : array
    {
        $formattedResults = [];
        if(empty($results)){
            return $formattedResults;
        }
        
        foreach( $results AS $result ) {
            $this->insertMenuItem($formattedResults, $result);
        }

        return $formattedResults;
    }

    /**
     * Returns the menu data
     *
     * @return array
     */
    public function getMenuData() : array
    {
        global $wpdb;

        $menuId = $this->getMenuId();

        $query = "SELECT
            p.ID, 
            p.post_title,
            objectIdMeta.meta_value AS object_id,
            urlMeta.meta_value AS url,
            parentMeta.meta_value AS parent_id
        FROM $wpdb->term_relationships tr
        INNER JOIN $wpdb->posts p ON tr.object_id = p.ID AND p.post_status = 'publish'
        INNER JOIN $wpdb->term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
        INNER JOIN $wpdb->terms t ON t.term_id = tt.term_id AND tt.term_id = $menuId
        LEFT JOIN $wpdb->postmeta objectIdMeta ON p.ID = objectIdMeta.post_id AND objectIdMeta.meta_key = '_menu_item_object_id' 
        LEFT JOIN $wpdb->postmeta urlMeta ON urlMeta.meta_key = '_menu_item_url' AND urlMeta.post_id = p.ID
        LEFT JOIN $wpdb->postmeta parentMeta ON parentMeta.meta_key = '_menu_item_menu_item_parent' AND parentMeta.post_id = p.ID
        ORDER BY p.menu_order;
        ";

        $menuItems = $wpdb->get_results($query);

        return $this->formatResults($menuItems);
    }

    /**
     * Get the menu markup as nested lists
     *
     * @param array $menuItem
     * @param string $subMenuClass
     * @return string
     */
    protected function getItemHtml(array $menuItem, string $subMenuClass) : string
    {
        $id = $menuItem['ID'];
        $title = $menuItem['post_title'];
        $url = $menuItem['url'];
        
        $children = $menuItem['children'];
        $childrenHtml = '';

        if (!empty($children)) {
            $childrenHtml .= "<ul class=\"$subMenuClass\">";
            foreach($children as $childMenuItem) {
                $childrenHtml .= $this->getItemHtml($childMenuItem, $subMenuClass);
            }
            $childrenHtml .= "</ul>";
        }

        $linkHtml = "<li class=\"menu-item-$id\"><a href=\"$url\">$title</a>$childrenHtml</li>";

        return $linkHtml;
    }

    /**
     * Get the menu markup as nested lists
     *
     * @param string $menuClass
     * @param string $subMenuClass
     * @return string
     */
    public function getHtml(string $menuClass, string $subMenuClass = 'sub-menu') : string
    {
        $menuData = $this->getMenuData();

        if (empty($menuData)) {
            return null;
        }

        $menuMarkup = '<ul class="' . $menuClass . '">';
        foreach ($menuData as $menuItem) {
            $menuMarkup .= $this->getItemHtml($menuItem, $subMenuClass);
        }
        $menuMarkup .= '</ul>';

        return $menuMarkup;
    }
}
