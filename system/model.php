<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace mvcCMS;

class Model implements \Iterator {

    private $stmh = NULL;
    private $sql = NULL;
    private $pdo = NULL;
    private $limit = "";
    private $bindings = array();
    private $current = NULL;
    private $position = -1;
    private $pagination;
    private $order = '';

    public function __construct($pdo, $sql = NULL) {
        if (is_a($pdo, "PDOStatement")) {
            $this->stmh = $sql;
        } elseif (is_string($sql)) {
            $this->pdo = $pdo;
	    $this->sql = $sql;
	    // Scan for nessecary bindings
	    if (preg_match_all( "/:([si])_([a-zA-Z][a-zA-Z0-9]*)/m", $sql, $b))
	    {
		    foreach( $b[0] as $idx => $bind )
		    {
			 // TODO Error handling
			 if ( $_GET[$b[2][$idx]] ) {
			    switch ( $b[1][$idx] ) {
			      case 'i': 
        				$this->bindings[$bind] = array($_GET[$b[2][$idx]], \PDO::PARAM_INT);
					break;
			      case 's': 
        				$this->bindings[$bind] = array($_GET[$b[2][$idx]], \PDO::PARAM_STR);
					break;
			    }
			 } else {
			   echo "Missing parameter ". $b[2][$idx]."<br>";
			 }
		    }
	    }
        }
    }

    public function rewind() {
        $this->position = 0;
        if ($this->sql) {
            $this->stmh = $this->pdo->prepare("$this->sql $this->order $this->limit  ");
	    foreach ($this->bindings as $key => $parms) {
                $this->stmh->bindValue($key, $parms[0], $parms[1]);
            }
        }
        if ($this->stmh) {
            $this->position = -1;
            if ($this->stmh->execute()) {
                $this->next();
            } else
                $this->stmh = NULL;
            //die("Failed to read model $v");
        }
    }

    public function current() {
        if ($this->position < 0)
            $this->rewind();
        return $this->current;
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        if ($this->stmh) {
            $this->current = $this->stmh->fetch(\PDO::FETCH_OBJ);
            ++$this->position;
        }
    }

    public function valid() {
        return ($this->stmh && $this->current);
    }

    public function order($table, $order = "asc") {
        // order cannot do binding
        $this->order = str_replace("'", '', "ORDER BY " . $this->pdo->quote($table) . ' ' . $this->pdo->quote($order));
    }

    // extra features
    public function paginate($items, $pagination = "pagination") {
        $this->limit = "LIMIT :offset,:limit";
        $this->bindings[':limit'] = array($items, \PDO::PARAM_INT);
        $this->pagination = $pagination;
        // get current page
        $current = 1;
        if (isset($_GET['pagination']))
            $current = $_GET[$pagination];
        if (isset($_POST['pagination']))
            $current = $_POST[$pagination];
        if (!is_numeric($current))
            $current = 1;
        $this->bindings[':offset'] = array(($current - 1) * $items, \PDO::PARAM_INT);
    }

    public function getpagination() {
        // Do we have limit set
        if (!isset($this->bindings[':limit'][0]))
            return "model->paginate not called";
        $limit = $this->bindings[':limit'][0];
        if (!isset($this->bindings[':offset'][0]))
            return "model->paginate not called";
        $limit = $this->bindings[':limit'][0];
        $current = $this->bindings[':offset'][0] / $limit + 1;
        $url = preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
        $get = preg_replace('/^.*\?/', '', $_SERVER['REQUEST_URI']);
        if ($url == $get)
            $get = ''; // if nog pagination etc.
            
// 
        // remove pagination
        $get = preg_replace("/$this->pagination=$current/", '', $get);
        $get = preg_replace("/\?\&/", '?', $get);
        if ($get == '')
            $get .= '?';
        else
            $get .= '&';
        // First we need to get the number of rows totally
        $stmh = $this->pdo->prepare("SELECT count(*) rows FROM ($this->sql)x");
        $stmh->execute();
        $rows = $stmh->fetch(\PDO::FETCH_OBJ)->rows;


        $firstpage = 1;
        $lastpage = ceil($rows / $limit);

        $html = "<ul class=\"pagination\">";
        // previous
        if ($firstpage == $current)
            $html .= "<li class=\"disabled\"><span>&laquo;</span></li>";
        else
            $html .= "<li><a href=\"$get$this->pagination=" . ($current - 1) . "\" rel=\"prev\">&laquo;</a></li>";
        // pages
        for ($page = $firstpage; $page <= $lastpage; $page++) {
            if ($page == $current)
                $html .= "<li class=\"active\"><span>$page</span></li>";
            else
                $html .= "<li><a href=\"$get$this->pagination=$page\">$page</a></li>";
        }
        // next
        if ($lastpage == $current)
            $html .= "<li class=\"disabled\"><span>&raquo;</span></li>";
        else
            $html .= "<li><a href=\"$get$this->pagination=" . ($current + 1) . "\" rel=\"prev\">&raquo;</a></li>";
        $html .= "</ul>";
        return $html;
        /*
          <ul class="pagination">
          <li class="disabled">        <span>&laquo;</span></li>
          <li class="active"><span>1</span></li>
          <li><a href="http://liberalismen.laravel.jegkalinux.dk/artikler?page=2">2</a></li>
          <li><a href="http://liberalismen.laravel.jegkalinux.dk/artikler?page=2" rel="next">&raquo;</a></li>
          </ul>
         */
    }

}
