<?php

/**
 * Box Plugin: Draw highlighting boxes around wiki markup
 *
 * Syntax:     <box width% classes|title>
 *   width%    width of the box, must use % unit
 *   classes   one or more classes used to style the box, several predefined styles included in style.css
 *   title     (optional) all text after '|' will be rendered above the main code text with a
 *             different style.
 *
 * Acknowledgements:
 *  Rounded corners based on snazzy borders by Stu Nicholls (http://www.cssplay.co.uk/boxes/snazzy)
 *  which is in turn based on nifty corners by Alessandro Fulciniti (http://pro.html.it/esempio/nifty/)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */
class syntax_plugin_box extends DokuWiki_Syntax_Plugin
{
    public $title_mode = false;

    // the following are used in rendering and are set by xhtmlBoxOpen()
    protected $xb_colours      = '';
    protected $content_colours = '';
    protected $title_colours   = '';

    public function getType()
    {
        return 'protected';
    }

    public function getAllowedTypes()
    {
        return array('container','substition','protected','disabled','formatting','paragraphs');
    }

    public function getPType()
    {
        return 'block';
    }

    // must return a number lower than returned by native 'code' mode (200)
    public function getSort()
    {
        return 195;
    }

    // override default accepts() method to allow nesting
    // - ie, to get the plugin accepts its own entry syntax
    public function accepts($mode)
    {
        if ($mode == substr(get_class($this), 7)) {
            return true;
        }

        return parent::accepts($mode);
    }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern('<box>(?=.*?</box.*?>)', $mode, 'plugin_box');
        $this->Lexer->addEntryPattern('<box\s[^\r\n\|]*?>(?=.*?</box.*?>)', $mode, 'plugin_box');
        $this->Lexer->addEntryPattern('<box\|(?=[^\r\n]*?\>.*?</box.*?\>)', $mode, 'plugin_box');
        $this->Lexer->addEntryPattern('<box\s[^\r\n\|]*?\|(?=[^\r\n]*?>.*?</box.*?>)', $mode, 'plugin_box');
    }

    public function postConnect()
    {
        $this->Lexer->addPattern('>', 'plugin_box');
        $this->Lexer->addExitPattern('</box.*?>', 'plugin_box');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {

        switch ($state) {
            case DOKU_LEXER_ENTER:
                $data = $this->boxstyle(trim(substr($match, 4, -1)));
                if (substr($match, -1) == '|') {
                    $this->title_mode = true;
                    return array('title_open',$data);
                } else {
                    return array('box_open',$data);
                }

            case DOKU_LEXER_MATCHED:
                if ($this->title_mode) {
                    $this->title_mode = false;
                    return array('box_open','');
                } else {
                    return array('data', $match);
                }

            case DOKU_LEXER_UNMATCHED:
                $handler->addCall('cdata', array($match), $pos);
                return false;

            case DOKU_LEXER_EXIT:
                $data = trim(substr($match, 5, -1));
                $title =  ($data && $data[0] == "|") ? substr($data, 1) : '';

                return array('box_close', $title);
        }
        return false;
    }

    /**
     * Create output
     */
    public function render($mode, Doku_Renderer $renderer, $indata)
    {

        if (empty($indata)) {
            return false;
        }
        list($instr, $data) = $indata;

        if ($mode == 'xhtml') {
            switch ($instr) {
                case 'title_open':
                    $this->title_mode = true;
                    $renderer->doc .= $this->xhtmlBoxOpen($data) . "<p class='box_title' {$this->title_colours}>";
                    break;

                case 'box_open':
                    if ($this->title_mode) {
                        $this->title_mode = false;
                        $renderer->doc .= "</p>\n<div class='box_content' {$this->content_colours}>";
                    } else {
                        $renderer->doc .= $this->xhtmlBoxOpen($data) .
                            "<div class='box_content' {$this->content_colours}>";
                    }
                    break;

                case 'data':
                    $renderer->doc .= $renderer->_xmlEntities($data);
                    break;

                case 'box_close':
                    $renderer->doc .= "</div>\n";

                    if ($data) {
                        $renderer->doc .= "<p class='box_caption' {$this->title_colours}>" .
                            $renderer->_xmlEntities($data) . "</p>\n";
                    }
                    $renderer->doc .= $this->xhtmlBoxClose();
                    break;
            }

            return true;
        }
        return false;
    }

    protected function boxstyle($str)
    {
        if (!strlen($str)) {
            return array();
        }

        $styles = array();

        $tokens = preg_split('/\s+/', $str, 9);                      // limit is defensive
        foreach ($tokens as $token) {
            if (preg_match('/^\d*\.?\d+(%|px|em|ex|pt|cm|mm|pi|in)$/', $token)) {
                $styles['width'] = $token;
                continue;
            }

            if (
                preg_match('/^(
              (\#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}))|        #colorvalue
              (rgb\(([0-9]{1,3}%?,){2}[0-9]{1,3}%?\))     #rgb triplet
              )$/x', $token)
            ) {
                $styles['colour'][] = $token;
                continue;
            }

            // restrict token (class names) characters to prevent any malicious data
            if (preg_match('/[^A-Za-z0-9_-]/', $token)) {
                continue;
            }
            $styles['class'] = (isset($styles['class']) ? $styles['class'] . ' ' : '') . $token;
        }
        if (!empty($styles['colour'])) {
            $styles['colour'] = $this->boxColours($styles['colour']);
        }

        return $styles;
    }

    protected function boxColours($colours)
    {
        $triplets = array();

      // only need the first four colours
        if (count($colours) > 4) {
            $colours = array_slice($colours, 0, 4);
        }
        foreach ($colours as $colour) {
            $triplet[] = $this->colourToTriplet($colour);
        }

      // there must be one colour to get here - the primary background
      // calculate title background colour if not present
        if (empty($triplet[1])) {
            $triplet[1] = $triplet[0];
        }

      // calculate outer background colour if not present
        if (empty($triplet[2])) {
            $triplet[2] = $triplet[0];
        }

      // calculate border colour if not present
        if (empty($triplet[3])) {
            $triplet[3] = $triplet[0];
        }

      // convert triplets back to style sheet colours
        $style_colours['content_background'] = 'rgb(' . join(',', $triplet[0]) . ')';
        $style_colours['title_background'] = 'rgb(' . join(',', $triplet[1]) . ')';
        $style_colours['outer_background'] = 'rgb(' . join(',', $triplet[2]) . ')';
        $style_colours['borders'] = 'rgb(' . join(',', $triplet[3]) . ')';

        return $style_colours;
    }

    protected function colourToTriplet($colour)
    {
        if ($colour[0] == '#') {
            if (strlen($colour) == 4) {
              // format #FFF
                return array(
                    hexdec($colour[1] . $colour[1]),
                    hexdec($colour[2] . $colour[2]),
                    hexdec($colour[3] . $colour[3])
                );
            } else {
              // format #FFFFFF
                return array(
                    hexdec(substr($colour, 1, 2)),
                    hexdec(substr($colour, 3, 2)),
                    hexdec(substr($colour, 5, 2))
                );
            }
        } else {
          // format rgb(x,y,z)
            return explode(',', substr($colour, 4, -1));
        }
    }

    protected function xhtmlBoxOpen($styles)
    {
        $class = 'class="box' . (isset($styles['class']) ? ' ' . $styles['class'] : '') . '"';
        $style = isset($styles['width']) ? "width: {$styles['width']};" : '';

        if (isset($styles['colour'])) {
            $colours = 'background-color: ' . $styles['colour']['outer_background'] . '; ';
            $colours .= 'border-color: ' . $styles['colour']['borders'] . ';';

            $this->content_colours = 'style="background-color: ' . $styles['colour']['content_background'] .
                '; border-color: ' . $styles['colour']['borders'] . '"';
            $this->title_colours = 'style="background-color: ' . $styles['colour']['title_background'] . ';"';
        } else {
            $colours = '';

            $this->content_colours = '';
            $this->title_colours = '';
        }

        if ($style || $colours) {
            $style = ' style="' . $style . ' ' . $colours . '"';
        }
        if ($colours) {
            $colours = ' style="' . $colours . '"';
        }

        $this->xb_colours = $colours;

        $html = "<div $class$style>\n";
        $html .= "  <b class='xtop'>" .
            "<b class='xb1'$colours></b>" .
            "<b class='xb2'$colours></b>" .
            "<b class='xb3'$colours></b>" .
            "<b class='xb4'$colours></b>" .
            "</b>\n";
        $html .= "  <div class='xbox'$colours>\n";

        return $html;
    }

    protected function xhtmlBoxClose()
    {

        $colours = $this->xb_colours;

        $html = "  </div>\n";
        $html .= "  <b class='xbottom'>" .
            "<b class='xb4'$colours></b>" .
            "<b class='xb3'$colours></b>" .
            "<b class='xb2'$colours></b>" .
            "<b class='xb1'$colours></b>" .
            "</b>\n";
        $html .= "</div>\n";

        return $html;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
