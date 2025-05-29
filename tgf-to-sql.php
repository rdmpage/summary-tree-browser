<?php

// Read a TGF file and export SQL

require_once (dirname(__FILE__) . '/n-tree.php');

//----------------------------------------------------------------------------------------
function tgf_to_tree($filename)
{
	$t = null;
	
	echo "-- getting tree\n";
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
	}
	
	return $t;
}

//----------------------------------------------------------------------------------------
function tree_to_sql(&$t)
{
	$n = new NodeIterator($t->GetRoot());
	$q = $n->Begin();
	while ($q)
	{
		// echo $q->GetLabel() . " " . $q->GetAttribute('weight') . " " . $q->GetAttribute('depth') . " " . $q->GetAttribute('score') . "\n";
		
		$keys = array();
		
		$values = array();
		
		$keys[] = 'id';
		$values[] = $q->GetId();
		
		if ($q->GetAncestor())
		{
			$keys[] = 'anc_id';
			$values[] = $q->GetAncestor()->GetId();			
		}
		
		// label may include external identifier if these aren't the same as the integer source->target ids
		if (preg_match('/(.*)\|(.*)/', $q->GetLabel(), $m))
		{
			$keys[] = 'external_id';
			$values[] = "'" . str_replace("'", "''", $m[1]) . "'";
		
			$keys[] = 'name';
			$values[] = "'" . str_replace("'", "''", $m[2]) . "'";
		}
		else
		{		
			$keys[] = 'name';
			$values[] = "'" . str_replace("'", "''", $q->GetLabel()) . "'";
		}
		
		$keys[] = 'weight';
		$values[] = $q->GetAttribute('weight');

		$keys[] = 'depth';
		$values[] = $q->GetAttribute('depth');

		$keys[] = 'score';
		$values[] = $q->GetAttribute('score');
		
		if (!is_null($q->GetAttribute('leaf_number')))
		{
			$keys[] = 'leaf_number';
			$values[] = $q->GetAttribute('leaf_number');
		}

		if (!is_null($q->GetAttribute('left')))
		{
			$keys[] = 'left';
			$values[] = $q->GetAttribute('left');
		}

		if (!is_null($q->GetAttribute('right')))
		{
			$keys[] = 'right';
			$values[] = $q->GetAttribute('right');
		}

		//echo $q->Dump();
		
		echo 'REPLACE INTO tree(' . join(',', $keys) . ') VALUES (' . join(',', $values) . ');' . "\n";
	
		$q = $n->Next();
	}
}

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

echo "-- reading $filename\n";
$t = tgf_to_tree($filename);

echo "-- converting to SQL\n";
tree_to_sql($t);



