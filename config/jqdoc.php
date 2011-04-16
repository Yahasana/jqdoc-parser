<?php

return array (
    'default' => array (
        'doc-dir'       => DOCROOT.'temp/jQdoc/',
        'raw-xml-uri'   => 'http://api.jquery.com/api/',//DOCROOT.'api.xml',
        'ui-components' => array (
            'http://docs.jquery.com/action/render/UI/API/1.8' => array (
                'Interactions'  => array ('Draggable','Droppable','Resizable','Selectable','Sortable'),
                'Widgets'       => array ('Accordion','Autocomplete','Button','Datepicker','Dialog','Progressbar','Slider','Tabs'),
                'Utilities'     => array ('Position'),
            ),
            'http://docs.jquery.com/action/render/UI/Effects'  => array (
                'Effects'       => array ('Effects','Blind','Clip','Drop','Explode','Fade','Fold','Puff','Slide','Scale','Bounce','Highlight','Pulsate','Shake','Size','Transfer'),
            )
        ),
    )
);
