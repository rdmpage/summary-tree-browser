# summary-tree-browser

Inspired by [Maximum Entropy Summary Trees](https://doi.org/10.1111/cgf.12094), see also [Maximum entropy summary trees to display higher classifications](https://iphylo.blogspot.com/2021/05/maximum-entropy-summary-trees-to.html). A use case for summary trees is to provide navigation in a taxonomic database. By condensing a large classification into a smaller representation, we better use space in the display.

Goal is to summarise a large, likely non-binary tree such as a taxonomic classification in the form of a smaller tree of a specified size (*k*). Summary trees introduce a special “other” node representing nodes not displayed. For example, many nodes in a large polytomy will be merged into a single “other” node. Note that there is only ever one “other” node at each node in the tree.

Summary trees require a function to score the nodes in a tree, such that nodes with a higher score get added to the summary tree first, lower scoring nodes get added only if there is still space to include them (i.e., if the summary tree is currently less than size *k*). The original work on summary trees used entropy, here I use simpler methods based on, say node size and distance from the root of the tree.


## Workflow

### TGF

Step 1 is to create a [Trivial Graph Format (TGF)](https://en.wikipedia.org/wiki/Trivial_Graph_Format) file for the classification. The format looks like this:

```
1 First node
2 Second node
#
1 2 Edge between the two
```

Node ids are integers, so for a classification where this is not always true we need a mechanism to ensure this. For cases such as the Catalogue of Life the node ids are encoded in base-29, so we can convert them to integers. 

### Database

We then read the TGF file and compute additional values for each node. These enable us to assign a score to each node, as well as  support faster queries.


