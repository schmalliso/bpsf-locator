<!DOCTYPE html>
<html>
<head>
<title>SFIT Analyser</title>

    <link href="./css/bootstrap.css" rel="stylesheet">
    <link href="./css/bootstrap-responsive.css" rel="stylesheet">
    <link href="./css/custom.css" rel="stylesheet">

    <link href="http://fonts.googleapis.com/css?family=Corben:bold" rel="stylesheet" type="text/css">
    <link href="http://fonts.googleapis.com/css?family=Nobile" rel="stylesheet" type="text/css">


</head>

<body>

<div class="container">
    <div class="row">
        <div class="span12">
            <h2>BPMN-SFIT Analyser</h2>
            <div>
                <h3>Description</h3>
                
<?php

$message = new bpmn('./attempt2.svg');

// this is the array that will track which SFIT components are present

$sfit = array(
    "profiles"      => array(),
    "networks"      => array(),
    "communities"   => array(),
    "ugc"           => array(),
    "comments"      => array()
);

$actionWords = array(
    "transmit", "calculate", "comment", "process", "handle", "compute", "synthesize", "synthesise", "input", "extract", "update", "order", "receive", "submit"
);

$groupWords = array(
    "individual", "user", "member", "staff"  
);

foreach($message->dataObject as $p => $q)
{
    $dataTitle = $q['title'];
    $message->fillDataObject($q['ref'], '#67CCFF');
    $sfit['comments'][] = $q['type'] . ": ". $dataTitle;
}


foreach($message->pool as $p => $q)
{
//    var_dump($message->pool->ref);
    $poolTitle = $q['title'];
    //var_dump($q['ref']->g[1]);
    foreach($groupWords as $words)
    {
        if(strpos(strtoupper($poolTitle), strtoupper($words)) !== false)
        {
            $message->fillPool($q['ref'], '67CCFF');
            $sfit['profiles'][] = $q['type'] . ": ". $poolTitle;
            $sfit['networks'][] = $q['type'] . ": ". $poolTitle;
            $sfit['communitites'][] = $q['type'] . ": ". $poolTitle;
        }
    }        
}


foreach($message->tasks as $k => $task)
{
   // echo "<p>" . $task['title'] . " ($k) </p>";   
    if (isset($task['terminate']))
    {
        foreach($task['terminate'] as $t)
        {
            
            $link = $message->links[$t];
            $linkType = $link['type'];
            
            $destination = $message->tasks[$message->links[$t]['origin']]['title'];

            // checking if it's Message Flow so that appropriate SFIT components can be suggested
            if ($linkType == "Message Flow")
            {
                $sfit['profiles'][] = $linkType . ": " . $destination;
                $sfit['networks'][] = $linkType . ": " . $destination;
                $sfit['communities'][] = $linkType . ": " . $destination;
                $message->fillElement($message->tasks[$k]['ref'], '#B4E6FF');
            }
            
            // printing out what's happening
          //  echo "<p>&nbsp;&nbsp;$linkType ($t) from $destination terminates here</p>";

        }
    }

    if (isset($task['origin']))
    {
        foreach($task['origin'] as $origin)
        {

            $destination = $message->tasks[$message->links[$origin]['terminate']]['title'];
            $link = $message->links[$origin];
            $linkType = $link['type'];
            // ************* //
            // SFIT CHECKING //
            // ************* //
            
            // message flow (i.e. sliding between two swimlanes) //
            if ($linkType == "Message Flow")
            {
                $sfit['profiles'][] = $linkType . ": " . $destination;
                $sfit['networks'][] = $linkType . ": " . $destination;
                $sfit['communities'][] = $linkType . ": " . $destination;
                //echo "I think I should highlight this\n";
                $message->fillElement($message->tasks[$k]['ref'], '#B4E6FF');
            }
          
         //   echo "<p>&nbsp;&nbsp;$linkType ($origin) to $destination originates here</p>";

        }    
    }   
    
}

echo "<h3>Flows between pools (message flow)</h3>";
$message->renderSVG();

foreach($message->tasks as $k => $task)
{
    
    // checking for tasks working on data, as determined by their verbs
    $taskName = $task['title'];
    
    foreach($actionWords as $word){
        if (strpos(strtoupper($taskName), strtoupper($word)) !== false)
        {
            $sfit['ugc'][] = $task['type'] . ": " .$taskName;
            $sfit['comments'][] = $task['type'] . ": " . $taskName;
            $message->fillElement($message->tasks[$k]['ref'], '#90B8CC');
        }
    }
}
echo "</div><div><h3>Acting on data</h3>";

$message->renderSVG();

echo "</div>

<div class=\"span12\">
<table class=\"table table-striped\">
    <tr>
    <th>SFIT Feature</th>
    <th>Matching Elements</th>
    </tr>";

foreach($sfit as $element => $tasks)
{
    echo "<tr><td>$element</td><td>" . implode ('<br /> ', $tasks) . "</td></tr>";
}
echo "</table>";

$message->outputSVG();


class bpmn
{
    public $path, $xml, $store, $links, $tasks, $dataObject, $pool;
    protected static $show = 0;
    
    
    //I'll need some sort of outputting majigger here
    
    public function __construct($path)
    {
        $this->path = $path;
        $this->xml = simplexml_load_file($path);
        $this->process();
        $this->interpret();
    }
    
    public function renderSVG()
    {
       // header('Content-Type: html');
        echo $this->xml->asXML();
    }
    
    public function renderXML()
    {
        // header('Content-Type: text/html');
        // echo "<html>\n<head>\n</head>\n<body>\n<pre>";
        echo htmlentities($this->xml->asXML());
    }
    
    public function outputSVG()
    {
        $this->xml->asXML('./outputs/_revised.svg');
    }
    
    public function fillElement($item, $color)
    {
 
        $rect = self::findRect($item);
        if (isset($rect['style']))
        {
            $rect['style'] = "fill: $color";    
        }else {
            $rect->addAttribute('style', "fill: $color");    
        }
                
    }
    
    public function fillDataObject($item, $color)
    {
        $path = $item->g->path;
        $path->addAttribute('style', "fill: $color");
    }
    
    public function fillPool($item, $color)
    {
        //var_dump($item->g->rect);
        $path = $item->g[1]->path;
        $path->addAttribute('style', "fill: $color");
    }
    
    public function process()
    {
        
        $this->store = array();
        foreach($this->xml->g->g as $e)
        {
            $id = (string) $e['id'];
            self::dprint("<hr>");
            
            preg_match('!translate\((-?\d+\.\d+),(-?\d+\.\d+)\)!', $e['transform'], $matches);
            $tx = $matches[1];
            $ty = $matches[2];
            
            //We have an element
            self::dprint( "{$e->title}: {$e->desc}\n");
            self::dprint( "Translate: $tx $ty \n");
            $type = $e->title;
            if (substr($type, 0, 4) == "Task")
            {
                $type = "Task";
                //var_dump($e);
            }
            
            if (substr($type, 0, 13) == "Sequence Flow")
            {
                $type = "Sequence Flow";
            }
            if (substr($type, 0, 11) == "Data Object")
            {
                $type = "Data Object";
            }
            switch($type) {
                
                case "Task":
                    self::dprint("find rect<br>\n");
                    $rect = self::findRect($e);
                    $this->tasks[$id] = array(
                            "type" => "task",
                            "title" => (string) $e->desc,
                            "tl" => array(
                                "x" => (float) ($rect['x'] + $tx),
                                "y" => (float) ($rect['y'] + $ty),
                                ),
                            "br" => array(
                                "x" => (float) ($rect['x'] + $rect['width'] + $tx),
                                "y" => (float)  ($rect['y'] + $rect['height'] + $ty),
                                ),
                            "ref" => $e
                        );
 
                    //var_dump($rect);
                    break;
                case "Message Flow":
                case "Sequence Flow":
                    self::dprint("find line<br>\n");
                    $path = self::findPath($e, $tx, $ty);
                    $path['type'] = (string) $type;
                    $this->links[$id] = $path;
                    break;
                case "Data Object":
                    $this->dataObject[$id] = array(
                        "type" => "data object",
                        "title" => (string) $e->desc,
                        "ref" => $e
                        );
                    //var_dump($this->dataObject[$id] );
                    //self::dprint($this, $level =1); 
                    break;
                case "Pool / Lane":
                    $this->pool[$id] = array(
                        "type" => "pool",
                        "title" => (string) $e->g[1]->desc,
                        "ref" => $e);
                    //var_dump($e);
                    //var_dump($this->pool[$id]);
                    //self::dprint($this->pool[$id]); 
                    
                default:
                    self::dprint("Unknown type: $type");
                    break;
            }
            
        }
    }
    
    public function interpret()
    {
        foreach($this->links as $lineID => $line)
        {
            self::dprint("Line Start\n");
            foreach($this->tasks as $taskID => $task)
            {
                self::dprint("Task start: {$task['title']}\n");
                
                //Check Start
                if(self::checkCollide($line['start'], $task))
                {
                    //Update line to know start
                    $this->links[$lineID]['origin'] = $taskID;
                    //Update shape to know line
                    $this->tasks[$taskID]['origin'][] = $lineID;
                    self::dprint("{$line['type']} ($lineID) starts at <b>{$task['title']}</b> ($taskID)\n", 1);
                    continue;
                }
                
                //Check End
                if(self::checkCollide($line['end'], $task))
                {
                    //Update line to know start
                    $this->links[$lineID]['terminate'] = $taskID;
                    //Update shape to know line
                    $this->tasks[$taskID]['terminate'][] = $lineID;
                    self::dprint("{$line['type']} ($lineID)  ends at <b>{$task['title']}</b> ($taskID)\n", 1);
                    continue;
                }
                
            }
        }
        
    }
    
    
    
    protected static function checkCollide($point, $rect, $fudge = 8)
    {
        //Four combinations
        $x = $point['x'];
        $y = $point['y'];
        
        $x1 = $rect['tl']['x'];
        $y1 = $rect['tl']['y'];
        
        $x2 = $rect['br']['x'];
        $y2 = $rect['br']['y'];
        self::dprint("Point: $x, $y");
        self::dprint("Box: $x1, $y1 --> $x2, $y2");
        if ($x > $x1 AND $x < $x2 AND $y > $y1 AND $y < $y2)
        {
            return true;
        }
        
        //Try Fudges
        //Fudge to top left
        $x -= $fudge;
        $y -= $fudge;
        self::dprint("Point: $x, $y");
        self::dprint("Box: $x1, $y1 --> $x2, $y2");
        if ($x > $x1 AND $x < $x2 AND $y > $y1 AND $y < $y2)
        {
            return true;
        }
        
        //Fudge to top right
        $x += $fudge * 2;
        self::dprint("Point: $x, $y");
        self::dprint("Box: $x1, $y1 --> $x2, $y2");
        if ($x > $x1 AND $x < $x2 AND $y > $y1 AND $y < $y2)
        {
            return true;
        }
        
        //Fudge to bottom right
        $y += $fudge * 2;
        self::dprint("Point: $x, $y");
        self::dprint("Box: $x1, $y1 --> $x2, $y2");
        if ($x > $x1 AND $x < $x2 AND $y > $y1 AND $y < $y2)
        {
            return true;
        }
        
        //Fudge to bottom right
        $x -= $fudge * 2;
        self::dprint("Point: $x, $y");
        self::dprint("Box: $x1, $y1 --> $x2, $y2");
        if ($x > $x1 AND $x < $x2 AND $y > $y1 AND $y < $y2)
        {
            return true;
        }
        
        return false;
    }
    
    protected static function findRect($e)
    {
        foreach($e->g as $i)
        {
            if (isset($i->rect))
            {
                return $i->rect;
            }
        }
    }
    protected static function findPath($e, $tx, $ty)
    {
        $path = (string) $e->path['d'];
        $bits = preg_split('![a-zA-Z]!', $path);
        array_shift($bits); //first element is empty;
        $start = self::pullxy(array_shift($bits));
        $x = $start['x'];
        $y = $start['y'];
        foreach($bits as $bit)
        {
            $b = self::pullxy($bit);
            $x = $b['x'];
            $y = $b['y'];
        }
        $start['x'] += $tx;
        $start['y'] += $ty;
        $final = array('start' => $start, 'end' => array('x' => $x + $tx, 'y' => $y + $ty));
        return $final;
    }
    
    
    protected static function pullxy($str)
    {
        $str = trim($str);
    
        list($bits['x'], $bits['y']) = $a = explode(' ',$str);
        return $bits;
    }
    
    protected static function dprint($message, $level = 3)
    {
        if (self::$show >= $level)
        {
            echo $message . "\n";
        }
    }
}
?>