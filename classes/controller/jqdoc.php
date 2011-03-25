<?php defined('SYSPATH') or die('No direct script access.');

set_time_limit(0);

class Controller_Jqdoc extends Controller {

    const FILE_CLEAN_REGEX = '#[\[\|=:\]]+#';

    /**
    *
    * @access protected
    * @var    bool $categories
    */
    protected $categories = array();

    public function before()
    {
        $config = Kohana::config('jqdoc.default');

        echo '<ol><li>Loading jQuery API raw xml</li>';

        $xml = file_get_contents($config['raw-xml-uri']);

        echo '<li>API raw xml has been loaded</li>';

        $this->jqdoc = simplexml_load_string($xml);
        $docroot = $config['doc-dir'].$this->jqdoc->getName();

        $this->docroot = $docroot.DIRECTORY_SEPARATOR;

        is_dir($this->docroot.'categories') OR mkdir($this->docroot.'categories', 0600, TRUE);

        $media = __DIR__.'/../../media';

        $handler = opendir($media);

        while ($file = readdir($handler))
        {
            if ($file !== '.' AND $file !== '..' AND ! is_dir("$media/$file")) {
                copy("$media/$file", $config['doc-dir'].$file);
            }
        }

        // tidy up: close the handler
        closedir($handler);

        if( ! is_file($config['doc-dir'].'jquery.min.js'))
        {
            $jquery = file_get_contents('https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js');
            file_put_contents($config['doc-dir'].'jquery.min.js', $jquery);
        }

        $this->template     = new View('jqdoc-template');
        $this->demo_html    = new View('jqdoc-demo');

        echo '<li>Loading jQuery UI API</li>';
        is_dir($this->docroot.'ui') OR mkdir($this->docroot.'ui', 0600, TRUE);
        $this->load_ui($this->docroot.'ui/', $config['ui-components']);

        echo '<li>Loading jQuery Types</li>';
        is_dir($this->docroot.'ui') OR mkdir($this->docroot.'ui', 0600, TRUE);
        $types = file_get_contents('http://docs.jquery.com/action/render/Types');
        file_put_contents($docroot.'/Types.htm', strtr($this->template, array(
            '{{path}}'      => '',
            '{{title}}'     => 'Types',
            '{{content}}'   => "<div style='padding:1em'>$types</div>"
        )));
    }

    public function action_index()
    {
        $this->parse_categories();

        foreach($this->parse_entries() as $method => $html)
        {
            $method_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $method);

            if(isset($this->properties[$method]) AND count($this->properties[$method]) > 1)
            {
                $nav = '<fieldset class="toc"><ul class="toc-list">';
                $id = str_replace('.', '_', $method);
                foreach($this->properties[$method] as $key => $signature)
                {
                    $nav .= '<li><h4><a href="#'.$id.$key.'">'."$method ( ".$signature[0][1]." )".'</a></h4><ul>';
                    foreach($signature as $sign)
                    {
                        list($v, $ar) = $sign;
                        $nav .= '<li><span class="versionAdded"><a href="categories/Version '.$v.'.htm">'.$v.'</a></span>'."$method ( $ar )".'</li>';
                    }
                    $nav .= '</ul></li>';
                }
                $html = $nav.'</ul></fieldset>'.$html;
            }

            file_put_contents($this->docroot.$method_fix.'.htm', strtr($this->template, array(
                '{{path}}'      => '',
                '{{title}}'     => $method,
                '{{content}}'   => $html
            )));
            echo '<li>'.$method_fix.'.htm generated</li>';
        }

        $index = '<br /><div class="entry-content"><h1 class="entry-title">jQuery API</h1><ul id="method-list">';
        foreach($this->categories as $key => $val)
        {
            $parent = '';
            foreach($val as $k => $v)
            {
                if(is_array($v))
                {
                    $v = implode('', $v);
                    $html = '<br /><div class="entry-content"><h1 class="entry-title">'.$k.'</h1><ul id="method-list">';
                    $html .= $v.'</ul></div>';

                    $k_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $k);

                    file_put_contents($this->docroot.'categories'.DIRECTORY_SEPARATOR.$k_fix.'.htm', strtr($this->template, array(
                        '{{title}}'     => $k,
                        '{{content}}'   => $html,
                        '{{path}}'      => '../'
                    )));
                }
                $index .= $v;
                $parent .= $v;
            }
            if($parent !== '')
            {
                $html = '<br /><div class="entry-content"><h1 class="entry-title">'.$key.'</h1><ul id="method-list">'.$parent.'</ul></div>';
                $key_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $key);

                file_put_contents($this->docroot.'categories'.DIRECTORY_SEPARATOR.$key_fix.'.htm', strtr($this->template, array(
                    '{{title}}'     => $key,
                    '{{content}}'   => $html,
                    '{{path}}'      => '../'
                )));
                if($key === 'Version') $version = substr($k, 8);
            }
        }
        $index .= '</ul></div>';

        file_put_contents($this->docroot.'categories'.DIRECTORY_SEPARATOR.'index.htm', strtr($this->template, array(
            '{{path}}'      => '../',
            '{{title}}'     => 'JQuery API',
            '{{content}}'   => $index
        )));

        $this->bookmark($version);
    }

    protected function parse_categories()
    {
        $categories = array();

        foreach($this->jqdoc->categories[0] as $cats)
        {
            foreach($cats->attributes() as $parent)
            {
                $parent = trim(((string) $parent));
                $categories[$parent] = array();

                foreach($cats->children() as $category)
                {
                    foreach($category->attributes() as $children)
                    {
                        $children = trim(((string) $children));
                        $categories[$parent][$children] = array();
                    }
                }
            }
        }

        $this->categories = $categories;
    }

    public function parse_entries()
    {
        $xx = 0;
        $html = array();
        foreach($this->jqdoc->entries[0] as $entries)
        {
            // attributes
            $attrs = $entries->attributes();
            $method = (string) $attrs->name;

            isset($prev_entry) OR $prev_entry = $method;

            $properties = array(
                'type'      => (string) $attrs->type,
                'return'    => (string) $attrs->return,
                'desc'      => (string) $entries->desc
            );

            $method_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $method);

            // signature
            $signatures = '<ul class="signatures">';
            foreach($entries->signature as $sign)
            {
                $args = '';
                $argc = array();
                $added = (string) $sign->added;
                $signatures .= '<li class="versionAdded">version added: <a href="categories/Version '.$added.'.htm">'.$added.'</a></li>';

                $cc = 0;
                foreach($sign->argument as $c => $argv)
                {
                    ++$cc;
                    $attr = $argv->attributes();
                    $argc[] = '<var>[ '.strtr(((string)$attr->type), array(', ' => ' | ')).' ]</var> '.$attr->name;
                    $args .= '<li class="arguement"><strong>'.$attr->name.' </strong>'.$argv->desc.'</li>';
                }

                if($properties['type'] === 'selector')
                {
                    $this->samples[$method] = (string) $entries->sample;
                    $function = 'JQuery("'.$this->samples[$method].'")';
                }
                else
                {
                    $pre = substr($method, 0, 6);
                    if($properties['type'] === 'method')
                        $function = (($pre !== 'jQuery' AND $pre !== 'event.') ? '.' : '').$method.'( '.implode(', ', $argc).' )';
                    else
                        $function = $method;
                }

                $this->properties[$method][$xx][] = array($added, implode(', ', $argc));

                $signatures .= '<li class="name"><strong>'.$function.'</strong></li>'.$args;
            }
            $signatures .= '</ul>';

            // category
            $categories = array();
            foreach($entries->category as $cats)
            {
                $attr = $cats->attributes();
                $cat = trim(((string) $attr->name));

                $li = '<li><h3 class="entry-title">'.$properties['return'].' <a href="../'.$method_fix.'.htm" title="link to '.$function
                    .'" rel="bookmark">'.$function.'</a></h3><span class="entry-meta"><span class="new"><a href="Version '.$added.'.htm">New in '.$added.' !</a></span></span><p class="desc">'.$properties['desc'].'</p></li>';

                if(isset($this->categories[$cat]))
                {
                    $this->categories[$cat][$method] = $li;
                }
                else
                {
                    foreach($this->categories as $key => $val)
                    {
                        if(isset($val[$cat]))
                        {
                            $this->categories[$key][$cat][$method] = $li;
                            break;
                        }
                    }
                }

                $categories[$cat] = '<a href="categories/'.$cat.'.htm">'.$cat.'</a>';
            }

            $id = str_replace('.', '_', $method).$xx;

            // desc
            if(isset($html[$method]))
            {
                $html[$method] .= '<h2 class="jq-clearfix section-title"><span class="returns">Returns: <a class="return" href="Types.htm#'
                    .$properties['return'].'">'.$properties['return'].'</a></span>'.$properties['return'].'<span class="name"> '.$function
                    .'</span><a name="'.$id.'"></a></h2><div class="entry-content"><div class="entry-meta">Categories: <span class="category">'.implode(', ', $categories).'</span></div>
                    <div class="desc"><strong>Description: </strong>'.$properties['desc'].'</div>'.$signatures;
                $multi_entry = TRUE;
            }
            else
            {
                $html[$method] = '<h2 class="jq-clearfix section-title"><span class="returns">Returns: <a class="return" href="Types.htm#'
                    .$properties['return'].'">'.$properties['return'].'</a></span>'.$properties['return'].'<span class="name"> '.$function
                    .'</span><a name="'.$id.'"></a></h2><div class="entry-content"><div class="entry-meta">Categories: <span class="category">'.implode(', ', $categories).'</span></div>
                    <div class="desc"><strong>Description: </strong>'.$properties['desc'].'</div>'.$signatures;
            }

            // longdesc
            $html[$method] .= '<div class="longdesc">';
            foreach($entries->longdesc[0] as $tags)
            {
                $name = $tags->getName();
                $tags = htmlspecialchars(((string) $tags));
                $html[$method] .= "<$name>$tags</$name>";
            }
            $html[$method] .= '</div>';

            // example
            $html[$method] .= '<h3>Examples:</h3><dl class="entry-examples">';
            foreach($entries->example as $example)
            {
                $css = $code = $demo = '';
                foreach($example as $tags)
                {
                    switch($tags->getName())
                    {
                        case 'desc':
                            $html[$method] .= '<dt class="desc"><h4>Example:</h4>'.$tags.'</dt>';
                            break;
                        case 'css':
                            $tags = preg_replace('/^\n+|^[\t\s]*\n+/m', '', ((string) $tags));
                            $css = "<style>\n".$tags.'</style>';
                            break;
                        case 'code':
                            $code = '<script>'.$tags.'</script>';
                            break;
                        case 'html':
                            $demo = (string) $tags;
                            break;
                    }
                }

                if($demo)
                {
                    $html[$method] .= '<dd class="example"><pre><code class="demo-code">'
                        .htmlspecialchars(strtr($this->demo_html, array(
                            '{{style}}' => $css, '{{html}}' => $demo, '{{script}}' => $code
                        ))).'</code></pre></dd><dd class="demo"><h4>Demo: </h4><div class="code-demo"></div></dd>';
                }
                else
                {
                    $html[$method] .= '<dd class="example"><pre><code>'.htmlspecialchars($code).'</code></pre></dd>';
                }
            }

            if($properties['type'] === 'selector')
            {
                $method_fix = preg_replace('/(?<=[^A-Z])([A-Z])/', ' ', $method_fix).' selector';
            }

            $html[$method] .= '</dl></div>';

            // don't know why disqus fail to load
            if(FALSE AND $prev_entry !== $method)
            {
                $html[$prev_entry] .=
                '<h1 id="comments" class="roundTop section-title">Comments</h1>
                <div style="display:block;width:100%"><div id="disqus_thread"></div></div>
                <script type="text/javascript">
                var disqus_url = "http://api.jquery.com/'.str_replace(' ', '-', $method_fix).'/";
                var disqus_container_id = "disqus_thread";
                var facebookXdReceiverPath = "http://api.jquery.com/wp-content/plugins/disqus-comment-system/xd_receiver.htm";
                </script>

                <script type="text/javascript">
                var DsqLocal = {
                trackbacks: [],
                trackback_url: "http://api.jquery.com/jQuery.when/trackback/"
                };
                </script>

                <script type="text/javascript">
                var ds_loaded = false,
                  top = jQuery("#comments").offset().top,
                  instructionsCloned = false;
                function check(){
                    if ( !ds_loaded && jQuery(window).scrollTop() + jQuery(window).height() > top ) {
                        jQuery.getScript("http://jquery.disqus.com/disqus.js?v=2.0&slug='.strtolower(str_replace(array(' ','-','.'), array('_','_',''), $method_fix)).'&pname=wordpress&pver=2.12");
                        ds_loaded = true;
                    } else if ( !instructionsCloned && document.getElementById("dsq-form-area") ) {
                      var instructions = jQuery("ul.comment-instructions");
                      instructions.clone().css({
                        backgroundColor: instructions.css("backgroundColor"),
                        borderWidth: instructions.css("borderWidth"),
                        padding: "1em",
                        margin: "1em 0"
                      }).prependTo("#dsq-form-area");
                      instructionsCloned = true;
                    }
                }
                try {jQuery(window).scroll(check); check();}catch(e){}
                </script>';
                $prev_entry = $method;
            }
            ++$xx;
        }

        return $html;
    }

    public function bookmark($version)
    {
        $jQuery  = "jQuery-UI-Reference-$version";
        $docroot = $this->jqdoc->getName();

        $hhp = <<<HHP
[OPTIONS]
Binary TOC=No
Binary Index=Yes
Compiled File=$jQuery.chm
Contents File=$jQuery.hhc
Index File=JQuery-API.hhk
Default Window=main
Default Topic=$docroot/categories/index.htm
Default Font=,,1
Full-text search=Yes
Auto Index=Yes
Language=
Title=jQuery API v$version
Create CHI file=No
Compatibility=1.1 or later
Error log file=_errorlog.txt
Full text search stop list file=
Display compile progress=Yes
Display compile notes=Yes

[WINDOWS]
main="jQuery API v$version                       (Generated by jQdoc, author: oalite@gmail.com)","$jQuery.hhc","$jQuery.hhk","api\categories\index.htm","api\categories\index.htm",,,,,0x7B520,222,0x1046,[10,10,640,450],0xB0000,,,,,,0

[FILES]
demo.js
jquery.min.js
logo_jquery_215x53.gif
style.css
index-blank.html
$docroot/categories/index.htm
$docroot/Types.htm

HHP;

        $toc = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN"><html><body>
   <object type="text/site properties">
     <param name="Window Styles" value="0x800025">
     <param name="comment" value="base:'.$docroot.'/categories/index.htm">
   </object><ul><li><object type="text/sitemap"><param name="Name" value="Home"></param>
   <param name="Local" value="'.$docroot.'/categories/index.htm"><param name="ImageNumber" value="22"></object></li>
   <li><object type="text/sitemap"><param name="Name" value="Types"></param>
   <param name="Local" value="'.$docroot.'/Types.htm"><param name="ImageNumber" value="17"></object></li>';

        $idx = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN"><html><body><ul>
        <li><object type="text/sitemap"><param name="Name" value="Types"><param name="Local" value="'.$docroot.'/Types.htm"></object></li>';

        foreach($this->categories as $key => $val)
        {
            if(empty($val)) continue;

            if(isset($this->samples[$key]))
            {
                $key_fix = $this->samples[$key];
            }
            else
            {
                $key_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $key);
            }

            $file = preg_replace(self::FILE_CLEAN_REGEX, '', $key);

            $hhp .= "$docroot/categories/$file.htm\n";

            $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.($key[0]===':'?':':'').$key_fix
                .'"><param name="Local" value="'.$docroot.'/categories/'.$file.'.htm"></object><ul>';

            $idx .= '<li><object type="text/sitemap"><param name="Name" value="'.($key[0]===':'?':':'').$key_fix
                .'"><param name="Local" value="'.$docroot.'/categories/'.$file.'.htm"></object></li>';

            foreach($val as $k => $v)
            {
                if(isset($this->samples[$k]))
                {
                    $key_fix = $this->samples[$k];
                }
                else
                {
                    $key_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $k);
                }

                $file = preg_replace(self::FILE_CLEAN_REGEX, '', $k);

                if(is_array($v))
                {

                    $hhp .= "$docroot/categories/$file.htm\n";

                    $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.($k[0]===':'?':':'').$key_fix
                        .'"><param name="Local" value="'.$docroot.'/categories/'.$file.'.htm"></object><ul>';

                    $idx .= '<li><object type="text/sitemap"><param name="Name" value="'.($k[0]===':'?':':'').$key_fix
                        .'"><param name="Local" value="'.$docroot.'/categories/'.$file.'.htm"></object></li>';

                    foreach($v as $kk => $vv)
                    {
                        if(isset($this->samples[$kk]))
                        {
                            $key_fix = $this->samples[$kk];
                        }
                        else
                        {
                            $key_fix = preg_replace(self::FILE_CLEAN_REGEX, '', $kk);
                        }

                        $file = preg_replace(self::FILE_CLEAN_REGEX, '', $kk);

                        $hhp .= "$docroot/$file.htm\n";

                        $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.($kk[0]===':'?':':'').$key_fix
                            .'"><param name="Local" value="'.$docroot.'/'.$file.'.htm"></object></li>';

                        $idx .= '<li><object type="text/sitemap"><param name="Name" value="'.($kk[0]===':'?':':'').$key_fix
                            .'"><param name="Local" value="'.$docroot.'/'.$file.'.htm"></object></li>';
                    }

                    $toc .= '</ul></li>';
                }
                else
                {
                    $hhp .= "$docroot/$file.htm\n";

                    $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.($k[0]===':'?':':'').$key_fix
                        .'"><param name="Local" value="'.$docroot.'/'.$file.'.htm"></object></li>';

                    $idx .= '<li><object type="text/sitemap"><param name="Name" value="'.($k[0]===':'?':':'').$key_fix
                        .'"><param name="Local" value="'.$docroot.'/'.$file.'.htm"></object></li>';
                }
            }

            $toc .= '</ul></li>';
        }

        if(isset($this->ui))
        {
            $toc .= '<li><object type="text/sitemap"><param name="Name" value="UI"><param name="ImageNumber" value="3"></object><ul>';
            foreach($this->ui as $key => $val)
            {
                if(isset($val[0]))
                {
                    $hhp .= "$docroot/ui/$val[0].htm\n";

                    $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.$key
                        .'"><param name="Local" value="'.$docroot.'/ui/'.$val[0].'.htm"></object><ul>';

                    $idx .= '<li><object type="text/sitemap"><param name="Name" value="'.$key
                        .'"><param name="Local" value="'.$docroot.'/ui/'.$val[0].'.htm"></object></li>';

                    unset($val[0]);
                }
                else
                {
                    $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.$key.'"></object><ul>';
                }
                foreach($val as $name => $file)
                {
                    $hhp .= "$docroot/ui/$file.htm\n";

                    $toc .= '<li><object type="text/sitemap"><param name="Name" value="'.$name
                        .'"><param name="Local" value="'.$docroot.'/ui/'.$file.'.htm"></object></li>';

                    $idx .= '<li><object type="text/sitemap"><param name="Name" value="'.$name
                        .'"><param name="Local" value="'.$docroot.'/ui/'.$file.'.htm"></object></li>';
                }
                $toc .= '</ul></li>';
            }
            $toc .= '</ul></li>';
        }

        $toc .= '</ul></body></html>';
        $idx .= '</ul></body></html>';

        file_put_contents($this->docroot.'../'.$jQuery.'.hhp', $hhp);
        echo '<li>'.$jQuery.'.hhp generated</li>';

        file_put_contents($this->docroot.'../'.$jQuery.'.hhc', $toc);
        echo '<li>'.$jQuery.'.hhc generated</li>';

        file_put_contents($this->docroot.'../'.$jQuery.'.hhk', $idx);
        echo '<li>'.$jQuery.'.hhk generated</li>';
    }

    protected function load_ui($path, array $components)
    {
        foreach($components as $uri => $coms)
        {
            foreach($coms as $name => $component)
            {
                is_dir($path.$name) OR mkdir($path.$name, 0600, TRUE);
                foreach($component as $c)
                {
                    $ui = file_get_contents("$uri/$c");
                    file_put_contents("{$path}{$name}/$c.htm", strtr($this->template, array(
                        '{{path}}'      => '../../',
                        '{{title}}'     => $c,
                        '{{content}}'   => $ui
                    )));
                    $this->ui[$name][$c] = "{$name}/$c";
                }
                if($name === 'Effects')
                {
                    $ui = file_get_contents("$uri");
                    file_put_contents("{$path}{$name}.htm", strtr($this->template, array(
                        '{{path}}'      => '../',
                        '{{title}}'     => $name,
                        '{{content}}'   => '<div style="padding:1em">'.str_replace('http://docs.jquery.com/UI/', '', $ui).'</div>'
                    )));
                    $this->ui['Effects'][0] = "{$name}";
                }
            }
        }
    }

} // End Jqdoc
