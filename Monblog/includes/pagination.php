<?php
/**
 * Classe de pagination
 */
class Pagination {
    private $total_items;
    private $items_per_page;
    private $current_page;
    private $total_pages;
    
    public function __construct($total_items, $items_per_page = 10, $current_page = 1) {
        $this->total_items = $total_items;
        $this->items_per_page = $items_per_page;
        $this->current_page = max(1, $current_page);
        $this->total_pages = ceil($total_items / $items_per_page);
    }
    
    /**
     * Retourne l'offset pour la requête SQL
     */
    public function getOffset() {
        return ($this->current_page - 1) * $this->items_per_page;
    }
    
    /**
     * Retourne la limite pour la requête SQL
     */
    public function getLimit() {
        return $this->items_per_page;
    }
    
    /**
     * Génère les liens de pagination HTML
     */
    public function render($base_url = '?', $page_param = 'page') {
        if ($this->total_pages <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Pagination">';
        $html .= '<ul class="pagination justify-content-center">';
        
        // Bouton précédent
        if ($this->current_page > 1) {
            $prev_url = $base_url . $page_param . '=' . ($this->current_page - 1);
            $html .= '<li class="page-item"><a class="page-link" href="' . $prev_url . '">&laquo; Précédent</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo; Précédent</span></li>';
        }
        
        // Pages numérotées
        $start_page = max(1, $this->current_page - 2);
        $end_page = min($this->total_pages, $this->current_page + 2);
        
        // Première page
        if ($start_page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . $page_param . '=1">1</a></li>';
            if ($start_page > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        // Pages centrales
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $this->current_page) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $page_url = $base_url . $page_param . '=' . $i;
                $html .= '<li class="page-item"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
            }
        }
        
        // Dernière page
        if ($end_page < $this->total_pages) {
            if ($end_page < $this->total_pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . $page_param . '=' . $this->total_pages . '">' . $this->total_pages . '</a></li>';
        }
        
        // Bouton suivant
        if ($this->current_page < $this->total_pages) {
            $next_url = $base_url . $page_param . '=' . ($this->current_page + 1);
            $html .= '<li class="page-item"><a class="page-link" href="' . $next_url . '">Suivant &raquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Suivant &raquo;</span></li>';
        }
        
        $html .= '</ul>';
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Retourne les informations de pagination
     */
    public function getInfo() {
        $start = ($this->current_page - 1) * $this->items_per_page + 1;
        $end = min($this->current_page * $this->items_per_page, $this->total_items);
        
        return [
            'current_page' => $this->current_page,
            'total_pages' => $this->total_pages,
            'total_items' => $this->total_items,
            'items_per_page' => $this->items_per_page,
            'start' => $start,
            'end' => $end
        ];
    }
}

/**
 * Fonction utilitaire pour créer une pagination
 */
function create_pagination($total_items, $items_per_page = 10, $current_page = 1) {
    return new Pagination($total_items, $items_per_page, $current_page);
}
?> 