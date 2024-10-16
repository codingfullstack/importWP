<?php
//************** */ PRINT CATEGORY LIST************
function printFunction($array){
    echo '<ul>';
        foreach ($array as $item) {
            echo '<li>' . esc_html($item['Name']) . '</li>';
        }
        echo '</ul>';
}