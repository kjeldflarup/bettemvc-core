<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

function view($view, $data = NULL) {
    $INCLUDEVIEW = $_SERVER['DOCUMENT_ROOT'] . "/../views/$view.php";
    if ($data)
        extract($data);
    if (file_exists($INCLUDEVIEW)) {
        ob_start();
        include($INCLUDEVIEW);
        return ob_get_clean();
    }
}
