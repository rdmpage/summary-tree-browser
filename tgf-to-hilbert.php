<?php

// Read a TGF file and export SVG

// Uses https://github.com/dliebner/php-polylabel to help place labels.

require_once (dirname(__FILE__) . '/colour.php');
require_once (dirname(__FILE__) . '/n-tree.php');

require('Polylabel.php');
use function \Polylabel\polylabel;

define('MASK_LEFT',   1);
define('MASK_RIGHT',  2);
define('MASK_TOP',    4);
define('MASK_BOTTOM', 8);

//----------------------------------------------------------------------------------------
function tgf_to_tree($filename)
{
	$t = null;
	
	$reader = new ReadTreeTGF($filename);
	
	if ($reader->Read())
	{		
		$t = $reader->GetTree();
		
		if (0)
		{
			$writer = new WriteDot($t);
			$output = $writer->Write();
			echo $output;
			exit();
		}
		
		/*
		
		// weights
		// depth
		// score
		// lca (eventually, main reason is for Kraken-like tool)
		// visit numbers (for range queries)
		// leaf numbers (for Hilbert curves)
		
		// weights
		// number of leaves descendant from node, =1 if node is a leaf
		echo "-- building weights\n";
		$t->BuildWeights($t->GetRoot());

		// depth
		// number of nodes on path to root
		echo "-- computing depth\n";
		$n = new PreorderIterator($t->GetRoot());
		$q = $n->Begin();
		while ($q)
		{
			$q->SetAttribute('depth', $n->get_stack_size());
			$q = $n->Next();
		}
		
		// score
		// by default score is weight of node (i.e., number of leaves in subtree rooted at
		// this node), divided by the depth of the node. This scheme gives more weight to 
		// nodes closer to the root
		echo "-- scoring nodes\n";
		$n = new PreorderIterator($t->GetRoot());
		$q = $n->Begin();
		while ($q)
		{
			$q->SetAttribute('score', $q->GetAttribute('weight'));				
			$q->SetAttribute('score', $q->GetAttribute('score')  / max(1, $q->GetAttribute('depth')));
			
			$q = $n->Next();
		}
				
		// leaf numbers
		// leaves are ordered from left to right, leftmost leaf = 0
		echo "-- ordering leaves\n";
		$left_count = 0;
		$n = new NodeIterator($t->GetRoot());
		$q = $n->Begin();
		while ($q)
		{
			if ($q->IsLeaf())
			{
				$q->SetAttribute('leaf_number', $left_count++);
			}

			$q = $n->Next();
		}		

		// visitor numbers
		echo "-- adding vistor numbers\n";
		$left_count = 0;
		$n = new VisitorIterator($t->GetRoot());
		$q = $n->Begin();
		while ($q)
		{
			$q = $n->Next();
		}		
		
		// OK we now have a tree with all the extra bits added
		*/
	}
	
	return $t;
}


//----------------------------------------------------------------------------------------
// return true if three points pt, pt2, and pt3 are colinear (fall along the same line)
function are_colinear($pt1, $pt2, $pt3)
{
	return ($pt2[0] - $pt1[0]) * ($pt3[1] - $pt1[1]) == ($pt2[1] - $pt1[1]) * ($pt3[0] - $pt1[0]);
}


// Based on https://github.com/mhyfritz/hilbert-curve/tree/master

// See also 
// Belavadi, P., Nakayama, J., & Calero Valdez, A. (2022). Visualizing Large Collections 
// of URLs Using the Hilbert Curve. In A. Holzinger, P. Kieseberg, A. M. Tjoa, & 
// E. Weippl (Eds.), Machine Learning and Knowledge Extraction (pp. 270–289). 
// Springer International Publishing. https://doi.org/10.1007/978-3-031-14463-9_18


//----------------------------------------------------------------------------------------
class HilbertCurve
{
	var $data = array();
	var $curve = array();
	var $item_to_curve = array();
	var $order = 0;
	var $num_points = 0;
	
	var $edges = array();
	
	var $polygons = array();
	
	//------------------------------------------------------------------------------------
	function __construct ($num_items, $padded = true)
	{
		$this->order = ceil(log(sqrt($num_items),2));
		
		$num_points = $this->get_num_points();
		$this->curve = array_fill(0, $num_points, null);
		
		for ($i = 0; $i < $num_items; $i++)
		{			
			if ($padded)
			{
				// padding out to fill square, Belavadi et al.
				$pos = round($i / $num_items * $num_points);
			}
			else
			{			
				// filling as far was data goes (empty bits left)
				$pos = $i;
			}
			
        	$this->curve[$pos] = $i;
        	$this->item_to_curve[$i] = $pos;
		}

		/*		
		foreach ($this->curve as $k => $v)
		{
			echo $k  . ' [' . join(',', $this->index_to_point($k)) . ']' . "\n";
		}
		*/
	
	}
	
	//------------------------------------------------------------------------------------
	// total number of points in the curve
	function get_num_points()
	{
		$n = 2 ** $this->order;
		return $n * $n;
	}

	//------------------------------------------------------------------------------------
	// number of rows/columns in the box holding the curve
	function get_dimensions()
	{
		$n = 2 ** $this->order;
		return $n;
	}
	
	//------------------------------------------------------------------------------------
	function offset($column, $row, $width) 
	{
    	return $row * $width + $column;
	}
	
	//------------------------------------------------------------------------------------
	function index_to_point($index)
	{
    	$n = 2 ** $this->order;
    	$point = ['x' => 0, 'y' => 0];
		for ($s = 1, $t = $index; $s < $n; $s *= 2) {
			$rx = 1 & ($t / 2);
			$ry = 1 & ($t ^ $rx);
			$this->rotate($point, $rx, $ry, $s);
			$point['x'] += $s * $rx;
			$point['y'] += $s * $ry;
			$t /= 4;
		}
		return $point;
	}
	
	//------------------------------------------------------------------------------------
	function point_to_index($point) 
	{
		$n = 2 ** $this->order;
		$index = 0;
		for ($s = $n / 2; $s > 0; $s = floor($s / 2)) {
			$rx = ($point['x'] & $s) > 0 ? 1 : 0;
			$ry = ($point['y'] & $s) > 0 ? 1 : 0;
			$index += $s * $s * ((3 * $rx) ^ $ry);
			$this->rotate($point, $rx, $ry, $n);
		}
		return $index;
	}
	
	
	//------------------------------------------------------------------------------------
	function rotate(&$point, $rx, $ry, $n) 
	{
		if ($ry !== 0) {
			return;
		}
		if ($rx === 1) {
			$point['x'] = $n - 1 - $point['x'];
			$point['y'] = $n - 1 - $point['y'];
		}
		[$point['x'], $point['y']] = [$point['y'], $point['x']];
	}
	
	//------------------------------------------------------------------------------------
	// Add borders to cells along curve start..end. Array $borders is passed by reference 
	// so we can update different sets of borders in the same array
	function add_curve_border(&$borders, $start, $finish)
	{		
		$size = 2 ** $this->order;
	
		for ($i = $start; $i <= $finish; $i++)
		{
			$mask = 0;
			
			$centre = $this->index_to_point($i);
			
			// are we on the margins of the box?
			if ($centre['x'] == 0) 
			{
				$mask |= MASK_LEFT;
			}
			
			if ($centre['x'] == $size - 1) 
			{
				$mask |= MASK_RIGHT;
			}
			
			if ($centre['y'] == 0) 
			{
				$mask |= MASK_TOP;
			}
			
			if ($centre['y'] == $size - 1) 
			{
				$mask |= MASK_BOTTOM;
			}
			
			// look at all possible neighbours of this cell
			if ($centre['x'] > 0)
			{
				// left cell
				$point = array('x' => $centre['x'] - 1, 'y' => $centre['y']);
				$index =  $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_LEFT;
				}
			}
	
			if ($centre['x'] < $size - 1)
			{
				// right cell
				$point = array('x' => $centre['x'] + 1, 'y' => $centre['y']);
				$index = $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_RIGHT;
				}
			}
				
			if ($centre['y'] > 0)
			{
				// top cell
				$point = array('x' => $centre['x'], 'y' => $centre['y'] - 1);
				$index =  $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_TOP;
				}
			}
	
			if ($centre['y'] < $size - 1)
			{
				// bottom cell
				$point = array('x' => $centre['x'], 'y' => $centre['y'] + 1);
				$index =  $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_BOTTOM;
				}
			}
			
			$borders[$i] = (Integer)$mask;
		}	
	}
	
	//------------------------------------------------------------------------------------
	// Clear list of edges that we use to store the graph for the polygon
	function clear_graph() 
	{
		$this->edges = array();
	}	

	//------------------------------------------------------------------------------------
	// Add edge to polygon graph
	function pts_to_edge($source_pt, $target_pt)
	{
		// node name is string representation of 
		$source = $this->pt_to_node_label($source_pt);
		$target = $this->pt_to_node_label($target_pt);
				
		if (!isset($this->edges[$source]))
		{
			$this->edges[$source] = array();
		}
		$this->edges[$source][] = $target;

		if (!isset($this->edges[$target]))
		{
			$this->edges[$target] = array();
		}
		$this->edges[$target][] = $source;
	}
	
	//------------------------------------------------------------------------------------
	// Convert a point to a node label for our polygon graph
	function pt_to_node_label($pt)
	{
		return '[' . join(',', $pt) . ']';
	}
	
	//------------------------------------------------------------------------------------
	// Convert a node label back to a pt
	function node_label_to_pt($label)
	{
		preg_match('/^\[(\d+),(\d+)\]/', $label, $m);
		
		$pt = array($m[1], $m[2]);

		return $pt;
	}
	
	//------------------------------------------------------------------------------------
	// Clear list of polygons
	function clear_polygons() 
	{
		$this->polygons = array();
	}	
	
	//------------------------------------------------------------------------------------
	// Store polygon
	function add_polygon($label, $polygon) 
	{
		$this->polygons[$label] = $polygon;
	}
	
	//------------------------------------------------------------------------------------
	function to_polygon($start, $finish)
	{		
		// visit every cell from $start to $finish and determine whether it needs a border

		$this->clear_graph();
		
		$size = 2 ** $this->order;
		
		$num_points = $this->get_num_points();
		
		for ($i = $start; $i <= $finish; $i++)
		{
			$centre = $this->index_to_point($i);
			
			$mask = 0;
			
			$centre = $this->index_to_point($i);
			
			// are we on the margins of the box?
			if ($centre['x'] == 0) 
			{
				$mask |= MASK_LEFT;
			}
			
			if ($centre['x'] == $size - 1) 
			{
				$mask |= MASK_RIGHT;				
			}
			
			if ($centre['y'] == 0) 
			{
				$mask |= MASK_TOP;
			}
			
			if ($centre['y'] == $size - 1) 
			{
				$mask |= MASK_BOTTOM;
			}
			
			// look at all possible neighbours of this cell
			if ($centre['x'] > 0)
			{
				// left cell
				$point = array('x' => $centre['x'] - 1, 'y' => $centre['y']);
				$index =  $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_LEFT;
				}
			}
	
			if ($centre['x'] < $size - 1)
			{
				// right cell
				$point = array('x' => $centre['x'] + 1, 'y' => $centre['y']);
				$index = $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_RIGHT;
				}
			}
				
			if ($centre['y'] > 0)
			{
				// top cell
				$point = array('x' => $centre['x'], 'y' => $centre['y'] - 1);
				$index =  $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_TOP;
				}
			}
	
			if ($centre['y'] < $size - 1)
			{
				// bottom cell
				$point = array('x' => $centre['x'], 'y' => $centre['y'] + 1);
				$index =  $this->point_to_index($point);
				
				if ($index < $start || $index > $finish)
				{
					$mask |= MASK_BOTTOM;
				}
			}

			// Now we know what we need to draw for each cell we need to convert to points
			// on lines.
			// We treat $centre as top left of the cell (e.g., 0,0) and the remaining
			// corners are (0,1), (1,0), and (1,1). For each mask pattern we add forward and
			// back edges to a graph where the nodes of the graph are vertices of the cells
			// with borders. We store forward and back edges as we don't yet know the 
			// direction of travel as we go around the polygon enclosing the set of cells.
							
			// line from 
			if ($mask & MASK_LEFT) 
			{
				$source_pt = $centre;
				$target_pt = $source_pt;
				$target_pt['y'] += 1;
				
				$this->pts_to_edge($source_pt, $target_pt);
			}

			if ($mask & MASK_RIGHT) 
			{
				$source_pt = $centre;
				$source_pt['x'] += 1;
				$target_pt = $source_pt;
				$target_pt['y'] += 1;
				
				$this->pts_to_edge($source_pt, $target_pt);
			}

			if ($mask & MASK_TOP) 
			{
				$source_pt = $centre;
				$target_pt = $source_pt;
				$target_pt['x'] += 1;
				
				$this->pts_to_edge($source_pt, $target_pt);
			}

			if ($mask & MASK_BOTTOM) 
			{
				$source_pt = $centre;
				$source_pt['y'] += 1;
				$target_pt = $source_pt;
				$target_pt['x'] += 1;
				
				$this->pts_to_edge($source_pt, $target_pt);
			}
			
		}	
		
		if (0)
		{
			echo '<pre>';
			print_r($this->edges);
			echo '</pre>';
		}		
		
		
		// Now we have a graph linking all the vertices, we need to simplfy this so
		// that only one edge connects each vertex.
		
		$nodes = array_keys($this->edges);
		
		//print_r($nodes);
		
		$n = count($nodes);
		
		$order = array();
		
		// start 
		$last = $nodes[0];		
		unset($this->edges[$last][1]);
		
		$order[] = $this->node_label_to_pt($last);
		
		// visit all edges
		$i = 1;		
		while ($i < $n)
		{
			$next = $this->edges[$last][0];			
			$order[] = $this->node_label_to_pt($next);
			if ($this->edges[$next][0] == $last)
			{
				unset($this->edges[$next][0]);
			}
			else
			{
				unset($this->edges[$next][1]);
			}			
			$this->edges[$next] = array_values($this->edges[$next]);
			
			$last = $next;			
			$i++;					
		}
		
		
		if (0)
		{
			echo '<pre>';
			print_r($this->edges);
			print_r($order);
			echo '</pre>';
		}		
		
		// Now we have a list of pts in order going around the polygon, but
		// we may have too many points as a line of, say, 10 cells, will have pts
		// for each cell along that line. So we want to simplify this to the minimum
		// number of points needed to define the polygon.
		// This routine is based on a ChatGPT answer in Python where we go around
		// the polygon testing whether points are colinear and only keep those that
		// aren't. Those define the boundary of the polygon.

		$n = count($order);		
		$simplified = array();
		
		for ($i = 0; $i < $n; $i++)
		{
			if ($i == 0)
			{
				$prev = $order[$n - 1];
			}
			else
			{
				$prev = $order[$i - 1];
			}
			$curr = $order[$i];
			$next = $order[($i + 1) % $n];
			
			if (!are_colinear($prev, $curr, $next))
			{
				$simplified[] = $curr;
			}
		}
		
		// close
		$simplified[] = $simplified[0];
		
		$polygon = new stdclass;
		
		// points that delimit polygon
		$polygon->pts = $simplified;
		
		// midpoint degree for computing colour
		$polygon->midpoint = ($finish - $start)/2 + $start;		
		$polygon->midpoint *= 360.0 / $num_points;
		
		// polylabel to find most isolabed point
		$polygon->pos = polylabel( array($polygon->pts), $precision = 50 );
		
		return $polygon;		
	}
	
	//------------------------------------------------------------------------------------
	// Render Hilbert curve as set of polygons
	function toSVG()
	{
		// need to figure out the scale we want		
		$unit_size = 100;
		$unit_size = 8;
		$size = $this->get_dimensions(); // number of cells in a row

		$width = $unit_size * $size;
		$height = $width;
	
		$svg = '<?xml version="1.0"?>' . "\n";
		$svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '">' . "\n";
		
		// need to determine font size in pixels that will display nicely
		$fontsize = round($width/80); // arbtrary
		
		$svg .= '<style type="text/css"><![CDATA[';
		$svg .= 'text {
		 font-size:' . $fontsize . 'px; 
		 alignment-baseline:
		 middle;text-anchor:middle;
		 font-family:sans-serif;
		  }';
		$svg .= ']]></style>';
		
		// polygons
		foreach ($this->polygons as $label => &$polygon)
		{
			$svg .= "<!-- $label -->\n";
			$points = '';
			foreach ($polygon->pts as $pt)
			{
				$points .=  ($unit_size * $pt[0]) . ',' . ($unit_size * $pt[1]) . ' ';
			}

			$svg .= '<polygon stroke-width="0.1" vector-effect="non-scaling-stroke" fill="none" stroke="black" points= "' . $points . '"';

			// fill colour
			$h = $polygon->midpoint;
			$c = 60;
			$l= 70;

			$rgb = fromHCL($h, $c, $l);
			
			$hex = array(
				str_pad(dechex(round($rgb[0], 0)), 2, '0', STR_PAD_LEFT),
				str_pad(dechex(round($rgb[1], 0)), 2, '0', STR_PAD_LEFT),
				str_pad(dechex(round($rgb[2], 0)), 2, '0', STR_PAD_LEFT)
				);

			$svg .= ' style="fill:#' . join("", $hex) . '"';
			
			$svg .= '/>' . "\n";
			
			// point where label can go
			//$svg .= '<circle cx="' . ($unit_size * $polygon->pos['x']) . '" cy="' . ($unit_size * $polygon->pos['y']) . '" r="2" />' . "\n";
			
		}
		
		// text labels, output separately from polygons so labels aren't obscured
		foreach ($this->polygons as $label => &$polygon)
		{		
			// draw label 
			$svg .= '<text x="' . ($unit_size * $polygon->pos['x']) . '" y="' . ($unit_size * $polygon->pos['y']) . '"';
			//$svg .= ' dominant-baseline="middle"';
			//$svg .= ' text-anchor="middle"';
			$svg .= '>';
			$svg .= $label;
			$svg .= '</text>';
		}		

		
		// highlight a square
		/*
		$id = 11100090;
		$p = $t->GetNode($id);
		
		echo $p->GetLabel() . "\n";
		
		$start =  $hilbert->item_to_curve[$p->GetAttribute('left_leaf')];
		$pt = $hilbert->index_to_point($start);
	

	
		$svg .= '<rect'
		. ' x="' . ($unit_size * $pt['x']) . '"'
		. ' y="' . ($unit_size * $pt['y']) . '"'
		
		. ' width="' . $unit_size . '"'
		. ' height="' . $unit_size . '"'
		. '/>';
		*/
		
		
		$svg .= '</svg>' . "\n";
		
		return $svg;
	}	
		


}



//----------------------------------------------------------------------------------------
// For every internal node get the "span" of the subtree rooted on that node in terms of
// "leaf order", where leaves are numbered 0, ... n-1 where n is the number of leaves.
// The root of the tree nas the span [0,n-1]
function get_leaf_spans($t)
{
	// map between leaf id and leaf order
	//$leaf_order_to_id = array();

	// ensure leaves "know" where they are in the 0, .. n-1 ordering
	$leaf_count = 0;
	$n = new NodeIterator($t->GetRoot());
	$q = $n->Begin();
	while ($q)
	{
		if ($q->IsLeaf())
		{
			$q->SetAttribute('leaf_number', $leaf_count);
			//$leaf_order_to_id[$leaf_count] = $q->GetId();	
			
			$leaf_count++;		
		}

		$q = $n->Next();
	}		
	
	// get spans for internal nodes in terms of leaf order
	$pre = new PreorderIterator($t->GetRoot());
	$q = $pre->Begin();
	while ($q)
	{		
		if ($q->IsLeaf())
		{
			$q->SetAttribute('left_leaf',  $q->GetAttribute('leaf_number'));
			$q->SetAttribute('right_leaf', $q->GetAttribute('leaf_number'));
		
		
			// Update spans on path between leaf and root
			$p = $q->GetAncestor();
			while ($p)
			{
				$p->SetAttribute('left_leaf', min($p->GetAttribute('left_leaf'), $q->GetAttribute('leaf_number')));
				$p->SetAttribute('right_leaf', max($p->GetAttribute('right_leaf'), $q->GetAttribute('leaf_number')));
				$p = $p->GetAncestor();
			}
		}
		else
		{
			// Internal node visited for first time, intialise
			$q->SetAttribute('left_leaf', $leaf_count);	
			$q->SetAttribute('right_leaf', 0);					
		}
		
		$q = $pre->Next();
	}
	
	if (0)
	{
		// debug
		$q = $n->Begin();
		while ($q)
		{
			echo $q->GetID() . ' ' . $q->GetAttribute('left_leaf') . ' ' . $q->GetAttribute('right_leaf') . "\n";
		
			$q = $n->Next();
		}		
		
		exit();
	}	
	
}


//----------------------------------------------------------------------------------------
function tree_to_hilbert(&$t)
{
	get_leaf_spans($t);
	
	// create curve
	$num_items = $t->GetNumLeaves();
	
	echo "Number of leaves=$num_items\n";

	$hilbert = new HilbertCurve($num_items);

	//------------------------------------------------------------------------------------
	// get list of child nodes at different "levels" i.e., distance from the root
	// level 1
	$level1 = array();
	
	$children = $t->GetRoot()->GetChildren();
	
	foreach ($children as $p)
	{
		$level1[] = $p->GetId();
	}
	
	// level 2
	$level2 = array();
	
	foreach ($level1 as $id)
	{
		$children = $t->GetNode($id)->GetChildren();
		foreach ($children as $p)
		{
			$level2[] = $p->GetId();
		}
	}
	
	// level 3
	$level3 = array();
	
	foreach ($level2 as $id)
	{
		$children = $t->GetNode($id)->GetChildren();
		foreach ($children as $p)
		{
			$level3[] = $p->GetId();
		}
	}
	
	// level 4
	$level4 = array();
	
	foreach ($level3 as $id)
	{
		$children = $t->GetNode($id)->GetChildren();
		foreach ($children as $p)
		{
			$level4[] = $p->GetId();
		}
	}
	
	// level 5
	$level5 = array();
	
	foreach ($level4 as $id)
	{
		$children = $t->GetNode($id)->GetChildren();
		foreach ($children as $p)
		{
			$level5[] = $p->GetId();
		}
	}

	// level 6
	$level6 = array();
	
	foreach ($level5 as $id)
	{
		$children = $t->GetNode($id)->GetChildren();
		foreach ($children as $p)
		{
			$level6[] = $p->GetId();
		}
	}
		
	//------------------------------------------------------------------------------------
	// Layout for a given level
	
	$level_to_display = $level1;
	
	// list of polygon objects
	$hilbert->clear_polygons();
	
	foreach ($level_to_display as $id)
	{
		$p = $t->GetNode($id);
	
		// need to know the border for each cluster rooted at $id
		
		// leftmost leaf		
		$start =  $hilbert->item_to_curve[$p->GetAttribute('left_leaf')];
		// rightmost leaf
		$finish = $hilbert->item_to_curve[$p->GetAttribute('right_leaf')];
				
		// echo $p->GetLabel() . " start=$start, finish=$finish\n";
		
		// Get border of partition as a polygon
		$polygon = $hilbert->to_polygon($start, $finish);
		
		$hilbert->add_polygon($p->GetLabel(), $polygon);
	}
	

	return $hilbert;
}

//----------------------------------------------------------------------------------------
/*
class HilbertDrawing()
{


	function 
	
	function render()
	{
	
	
	}

}
*/


//----------------------------------------------------------------------------------------
	
	
$filename = '';
if ($argc < 2)
{
	echo "Usage: " . basename(__FILE__) . " <filename>\n";
	exit(1);
}
else
{
	$filename = $argv[1];
}

$basename = basename($filename, '.tgf');		

echo "Reading tree...\n";
$t = tgf_to_tree($filename);

echo "Hilbert..\n";
$h = tree_to_hilbert($t);

$svg = $h->toSVG();

echo $svg;

?>


