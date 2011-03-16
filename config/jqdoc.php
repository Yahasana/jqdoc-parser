<?php

return array (
    'default' => array (
        'doc-dir'       => DOCROOT.'temp/jQdoc/',
        'raw-xml-uri'   => 'api.xml', //'http://api.jquery.com/api/'
        'ui-html-uri'   => 'http://docs.jquery.com/action/render/UI/',
        'ui-version'    => array ('1.8','1.7.2'),
        'ui-components' => array (
            'Interactions'  => array ('Draggable','Droppable','Resizable','Selectable','Sortable'),
            'Widgets'       => array ('Accordion','Autocomplete','Button','Datepicker','Dialog','Progressbar','Slider','Tabs'),
            'Utilities'     => array ('Position'),
            'Effects'   => array (),
        ),
    )
);
