function ajax(url, options) 
{
        var data    = options.data   || "";
        var method  = options.method || "POST";
        var success = options.success;
        var failure = options.failure;

        var xhr = new XMLHttpRequest();

        xhr.open("POST", url, true);

        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                        if (xhr.status !== 200) {
                                console.log("["+xhr.status+"]"+xhr.responseText);
                                if (typeof failure === "function") {
                                        success(xhr.responseText, xhr);
                                }
                        } else {
                                console.log("["+xhr.status+"]");
                                if (typeof success === "function") {
                                        success(xhr.responseText, xhr);
                                }
                        }
                }
        };
            
        xhr.send(data);
}



ajax("/beta/server.php", {
        "method":"POST",
        "success":function(data, xhr) {

                data = JSON.parse(data);

                var str = "";

                for (key in data) {
                        str += "<option>"+key+"</option>";
                }
                document.getElementById("title-select").innerHTML = str;
        },
});






var title = document.getElementById("title-select");

title.addEventListener("change", function(event) {
        
        var title_val = title.options[title.selectedIndex].text;

        ajax("/beta/server.php", {
                "data":"title="+title_val+"",
                "method":"POST",
                "success":function(data, xhr) {

                        data = JSON.parse(data);

                        var str = "";

                        for (key in data) {
                                str += "<option>"+key+"</option>";
                        }
                        document.getElementById("section-select").innerHTML = str;
                },
        });
});

var section = document.getElementById("section-select");

section.addEventListener("change", function(event) {
        
        var title_val   = title.options[title.selectedIndex].text;
        var section_val = section.options[section.selectedIndex].text;

        ajax("/beta/server.php", {
                "data":"title="+title_val+"&section="+section_val+"",
                "method":"POST",
                "success":function(data, xhr) {
                        document.getElementById("description").innerHTML = data;
                },
        });

        ajax("/beta/server.php", {
                "data":"title="+title_val+"&section="+section_val+"&citationgraph=1",
                "method":"POST",
                "success":function(data, xhr) {
                        console.log("got response");
                        draw_graph(JSON.parse(data));
                },
        });
});

var searchbutton = document.getElementById("searchbutton");
var search_form  = document.getElementById("search");

function fetch_search_results(event)
{
        var search_text = document.getElementById("main-search").value;
                        
        console.log("sending some data!");

        ajax("/beta/server.php", {
                "data":"search="+search_text+"",
                "method":"POST",
                "success":function(data, xhr) {

                        console.log("got some data!");

                        data = JSON.parse(data);

                        var str = "<span class='search-results-info'>Displaying <b>"+data.results.length+"</b> out of <b>"+data.total_matches+"</b> results for '"+data.search_query+"'</span>";
			
			str += "<ol>";

                        for (i=0; i<data.results.length; i++) {
                                str += "<li><span class='index'>"+data.results[i].title+"."+data.results[i].section+"</span><span class='desc'>"+data.results[i].heading+"</span></li>";
                        }
                        str += "</ol>";

                        document.getElementById("search_results").innerHTML = str;
                },
        });
}

searchbutton.addEventListener("click", fetch_search_results);
search_form.addEventListener("submit", function(event) {
	fetch_search_results();
	event.preventDefault();
	return false;
});


function load_prelim()
{
        var json = {
                "nodes":[
                        {"id":0, "group":0},
                        {"id":1, "group":0},
                        {"id":2, "group":0},
                        {"id":3, "group":0},
                        {"id":4, "group":0},
                        {"id":5, "group":0},
                        {"id":6, "group":0},
                        {"id":7, "group":0},
                        {"id":8, "group":0},
                        {"id":9, "group":0}
                ],
                "links":[
                        {"source":0, "target":1, "weight":1},
                        {"source":0, "target":2, "weight":1},
                        {"source":1, "target":2, "weight":2},
                        {"source":3, "target":4, "weight":2},
                        {"source":4, "target":5, "weight":1},
                        {"source":1, "target":4, "weight":2},
                        {"source":3, "target":2, "weight":1},
                        {"source":5, "target":4, "weight":2},
                        {"source":6, "target":3, "weight":2},
                        {"source":6, "target":5, "weight":1},
                        {"source":7, "target":8, "weight":1},
                        {"source":7, "target":8, "weight":1},
                        {"source":8, "target":9, "weight":5},
                        {"source":3, "target":9, "weight":1}
                ]
        };

        draw_graph(json);
}

function draw_graph(json)
{
        console.log("in draw_graph");
        console.log(json);
        var svg = d3.select("svg");

        svg.html(""); /* empty the node */
	//svg.call(zoom);


	var g = svg.append("g");


        var width  = +svg.node().getBoundingClientRect().width;
        var height = +svg.node().getBoundingClientRect().height;


	function zoomed() {
		console.log("zooming?");
	  g.attr("transform", d3.event.transform);
	}

	var zoom = d3.zoom()
		.scaleExtent([1 / 2, 8])
		.on("zoom", zoomed);

	function dragged(d) {
		console.log("dragging?");
	  d3.select(this).attr("cx", d.x = d3.event.x).attr("cy", d.y = d3.event.y);
	}

	var drag = d3.drag()
		.on("drag", dragged);

	svg.call(zoom);
	g.call(drag);

        var simulation = d3.forceSimulation()
                .force("charge", d3.forceManyBody().strength(-200))
                .force("link", d3.forceLink().id(function(d) { return d.id; }).distance(40))
                .force("x", d3.forceX(width / 2))
                .force("y", d3.forceY(height / 2))
                .on("tick", ticked)
		//.origin(function(d) { return d; })

        var link = g.selectAll(".link");
        var node = g.selectAll(".node");

        simulation.nodes(json.nodes);
        simulation.force("link").links(json.links);

        link = link
                .data(json.links)
                .enter().append("line")
                .attr("class", "link");

        node = node
                .data(json.nodes)
                .enter().append("circle")
                .attr("class", "node")
                .attr("r", 6)
                .style("fill", function(d) { return d.id; });
		//.call(drag);

        function ticked() {
          link.attr("x1", function(d) { return d.source.x; })
              .attr("y1", function(d) { return d.source.y; })
              .attr("x2", function(d) { return d.target.x; })
              .attr("y2", function(d) { return d.target.y; });

          node.attr("cx", function(d) { return d.x; })
              .attr("cy", function(d) { return d.y; });
        }
}
