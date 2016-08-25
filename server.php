<?php 

class DB extends SQLite3
{
        function __construct($filename)
        {
                $this->open($filename);
        }
}


function array_to_json($array)
{
        $json = json_encode($array, JSON_PRETTY_PRINT|JSON_PARTIAL_OUTPUT_ON_ERROR);

	switch (json_last_error()) {
        case JSON_ERROR_NONE:
                break;
        case JSON_ERROR_DEPTH:
                throw new Exception("[JSON] Maximum stack depth exceeded");
                break;
        case JSON_ERROR_STATE_MISMATCH:
                throw new Exception("[JSON] Underflow or the modes mismatch");
                break;
        case JSON_ERROR_CTRL_CHAR:
                throw new Exception("[JSON] Unexpected control character found");
                break;
        case JSON_ERROR_SYNTAX:
                throw new Exception("[JSON] Syntax error, malformed JSON");
                break;
        case JSON_ERROR_UTF8:
                throw new Exception("[JSON] Malformed UTF-8 characters");
                break;
        default:
                throw new Exception("[JSON] Unknown error");
                break;
    	}

	return $json;
}

function list_titles_json()
{
        $db = new DB("usc.db");

        $arr = array();

        $res = $db->query("SELECT DISTINCT title FROM nodes");

        if ($res === FALSE) {
                throw new Exception("[SQLITE] Database query failed");
        }

        while ($row = $res->fetchArray()) { 
                $arr[$row["title"]] = 1;
        }

        return array_to_json($arr);
}

function list_sections_json($title)
{
        $db = new DB("usc.db");

        $arr = array();

        $title = SQLite3::escapeString($title);

        $res = $db->query("SELECT section FROM nodes WHERE title = '$title'");

        if ($res === FALSE) {
                throw new Exception("[SQLITE] Database query failed");
        }

        while ($row = $res->fetchArray()) { 
                if (!isset($arr[$row["section"]])) {
                        $arr[$row["section"]] = 1;
                }
        }

        return array_to_json($arr);
}

function get_metadata_json($title, $section)
{
        $db = new DB("usc.db");

        $arr = array();

        $title   = SQLite3::escapeString($title);
        $section = SQLite3::escapeString($section);

        $res = $db->query("SELECT heading FROM nodes WHERE title = '$title' AND section = '$section'");

        if ($res === FALSE) {
                throw new Exception("[SQLITE] Database query failed");
        }

        $text = "";

        while ($row = $res->fetchArray()) { 
                $text = utf8_encode($row["heading"]);
        }

        return $text ? $text : "[No heading available]";
}

function title_section_json()
{
        $db = new DB("usc.db");

        $arr = array();

        $res = $db->query("
                SELECT title
                FROM nodes 
        ");

        if ($res === FALSE) {
                throw new Exception("[SQLITE] Database query failed");
        }

        while ($row = $res->fetchArray()) { 
                if (!isset($arr[$row["title"]])) {
                        $arr[$row["title"]] = array();
                }
                //if (!isset($arr[$row["title"]][$row["section"]])) {
                        //$arr[$row["title"]][$row["section"]] = utf8_encode($row["heading"]);
                //}
        }

        return array_to_json($arr);
}


function get_citation_json($title, $section)
{
        $db = new DB("usc.db");

        $arr = array();

        $title   = SQLite3::escapeString($title);
        $section = SQLite3::escapeString($section);

        $res = $db->query("
                SELECT n0.rowid, n1.rowid
                FROM edges
                JOIN nodes as n0
                        ON n0.url = edges.source_url
                        AND n0.title = '$title'
                        AND n0.section = '$section'
                JOIN nodes as n1
                        ON n1.url = edges.target_url
        ");

        //$res = $db->query("
                //SELECT n0.rowid, n1.rowid
                //FROM edges
                //JOIN nodes as n0
                        //ON n0.url = edges.source_url
                        //AND n0.title = '$title'
                        //AND n0.section = '$section'
                //JOIN nodes as n1
                        //ON n1.url = edges.target_url
                //UNION
                //SELECT n0.rowid, n1.rowid
                //FROM edges
                //JOIN nodes as n2
                        //ON n2.url = edges.target_url
                        //AND n2.title = '$title'
                        //AND n2.section = '$section'
                //JOIN nodes as n3
                        //ON n3.url = edges.source_url
        //");

        $citation = array();

        while ($row = $res->fetchArray()) {
                if (isset($citation[$row[0]])) {
                        $citation[$row[0]][$row[1]] = 1;
                } else {
                        $citation[$row[0]] = array();
                        $citation[$row[0]][$row[1]] = 1; 
                }
        }

        $nodes = array();
        $links = array();

        $added = array();

        foreach ($citation as $source_id => $targets) {
                $nodes[] = array("id"=> $source_id, "group"=>"0");
        }

        foreach ($targets as $target_id => $etc) {
                if (!isset($cite[$target_id]) && !isset($added[$target_id])) {
                        $nodes[] = array("id"=> $target_id, "group"=>"0");
                        $added[$target_id] = true;
                }

                $links[] = array("source" => $source_id, "target"=>$target_id, "weight"=>1);
        }


        return array_to_json(array("nodes"=>$nodes, "links"=>$links));
}

function get_search_results($search)
{
        $db = new DB("usc.db");

        $arr = array();

        $search = SQLite3::escapeString($search);

        $res = $db->query("
                SELECT COUNT(*)
                FROM search 
                WHERE text MATCH '$search'
        ");

        while ($row = $res->fetchArray()) {
                $total_matches = $row[0];
        }

        $res = $db->query("
                SELECT url, heading, title, section
                FROM search 
                WHERE text MATCH '$search'
                LIMIT 5 
        ");

        $arr = array();

        while ($row = $res->fetchArray()) {
                $arr[] = array(
                        "url" => utf8_encode($row[0]),
                        "heading" => utf8_encode($row[1]),
                        "title" => utf8_encode($row[2]),
                        "section" => utf8_encode($row[3])
                );
        }

        return array_to_json(array(
                "search_query"  => $search,
                "total_matches" => $total_matches,
                "results"       => $arr
        ));
}

function request($key)
{
        if (isset($_POST[$key])) {
                return $_POST[$key];
        }
        if (isset($_GET[$key])) {
                return $_GET[$key];
        }
        if (!isset($_GET[$key]) && !isset($_POST[$key])) {
                return NULL;
        }
}


try {
        $title   = request("title");
        $section = request("section");
        $search  = request("search");
        
        if ($search !== NULL) {
                $output = get_search_results($search);
        } else if ($title !== NULL) {
                if ($section !== NULL) {
                        if (request("citationgraph")) {
                                $output = get_citation_json($title, $section);
                        } else {
                                $output = get_metadata_json($title, $section);
                        }
                } else {
                        $output = list_sections_json($title);
                }
        } else {
                $output = list_titles_json();
        }

        header("Content-Type: application/json");
        echo $output;
} catch (Exception $e) {
        http_response_code(500);
        echo "[ERR]" . $e->getMessage();
}

?>
