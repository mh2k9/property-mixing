<?php

/**
 * @script: SearchAlgorithm.php
 * @author: Mahadi Hasan
 * @E-mail: mahm2k9@gmail.com
 * @time: 03/10/2016 09:26:35 PM
 */

class PropertyMixing
{
    private $propertyLimitPerPage = 10;

    private $numberFound = [
        'bc' => [ 'count' => 50, 'ratio' => 5, ],
        'ha' => [ 'count' => 13, 'ratio' => 3, ],
        'vr' => [ 'count' => 11, 'ratio' => 2, ],
    ];

    public $pagination = [];

    /**
     * For debugging
     * @param $data
     */
    public function debug ( $data )
    {
        print '<pre>';
        print_r($data);
        print '</pre> <hr>';
    }

    /**
     * Reorder feed ratios. Feed ratio can be changed dynamically. ie., if HA have feed ratio 6 but it have only
     * three properties then its ratio will be changed to 3 and the rest ratio of HA will be added to next or first
     * feed to fill-up the number of properties for a page. For last page property count may have less number of count.
     */
    private function reOrderRatio()
    {
        $feedNames = array_keys($this->numberFound);
        $lastFeedName = end( $feedNames );

        for ( $i = 0; $i < count( $this->numberFound ); $i++ )
        {
            $propertyCount = $this->numberFound[ $feedNames[ $i ] ][ 'count' ];
            $propertyRatio = $this->numberFound[ $feedNames[ $i ] ][ 'ratio' ];

            $checkForLackProperty = $propertyCount - $propertyRatio;

            if ( $checkForLackProperty < 0 )
            {
                $propertyFound = $propertyCount;
                $this->numberFound[ $feedNames[ $i ] ][ 'ratio' ] = $propertyFound;

                if ( $feedNames[ $i ] != $lastFeedName )
                {
                    $this->numberFound[ $feedNames[ $i + 1 ] ][ 'ratio' ] -= $checkForLackProperty;
                }
                else
                {
                    $topRatioFeedPropertyCount = $this->numberFound[ $feedNames [ 0 ] ][ 'count' ];
                    $topRatioFeedPropertyRatio = $this->numberFound[ $feedNames [ 0 ] ][ 'ratio' ] - $checkForLackProperty;

                    if ( $topRatioFeedPropertyCount - $topRatioFeedPropertyRatio < 0 )
                    {
                        $this->numberFound[ $feedNames [ 0 ] ][ 'ratio' ] = $topRatioFeedPropertyCount;
                    }
                    else
                    {
                        $this->numberFound[ $feedNames [ 0 ] ][ 'ratio' ] = $topRatioFeedPropertyRatio;
                    }
                }
            }
        }
    }

    /**
     * Generate Pagination. This method will generate a data array contains start, end index of each feed.
     * Limit and offset of each page. Tail of head based on total number of properties.
     * @return array <p>Pagination</p>
     */
    public function generatePagination()
    {
        $pageNumber = 1;

        while( true )
        {
            $this->reOrderRatio();

            $feedNames = array_keys($this->numberFound);

            $propertyCountForPerPage = 0;

            foreach ( $this->numberFound as $feed => $prop )
            {
                $propertyRatio = $prop[ 'ratio' ];
                $property_count = $prop[ 'count' ];
                $propertyCountForPerPage += $property_count;

                if ( $property_count )
                {
                    # get current traverse head & tail
                    $traverseHead = isset ( $this->pagination [ $pageNumber - 1 ][ $feed ][ 'head' ] ) ?
                        $this->pagination [ $pageNumber - 1 ][ $feed ][ 'head' ] + $propertyRatio:
                        $propertyRatio;

                    $traverseTail = isset ( $this->pagination [ $pageNumber - 1 ][ $feed ][ 'head' ] ) ?
                        $this->pagination [ $pageNumber - 1 ][ $feed ][ 'head' ] :
                        0;

                    # overwrite properties count
                    $this->numberFound[ $feed ][ 'count' ] -= $propertyRatio;

                    # set pagination properties
                    $this->pagination[ $pageNumber ][ $feed ] = [
                        'tail' => $traverseTail,
                        'head' => $traverseHead,
                        'ratio' => $propertyRatio,
                    ];
                }
            }

            # set limit, offset
            $allFeedTails = array_column( $this->pagination[ $pageNumber ], 'tail' );
            $allFeedHeads = array_column( $this->pagination[ $pageNumber ], 'head' );

            $this->pagination[ $pageNumber ][ 'offset' ] = min( $allFeedTails );
            $this->pagination[ $pageNumber ][ 'limit' ] = max( $allFeedHeads ) - min( $allFeedTails );

            # start, end index of retrieve data array
            foreach ($this->pagination[ $pageNumber ] as $feed => $prop)
            {
                if ( in_array($feed, $feedNames) )
                {
                    $offset = $this->pagination[ $pageNumber ][ 'offset' ];
                    $ratio = $prop[ 'ratio' ];
                    $tail = $prop[ 'tail' ];

                    $this->pagination[ $pageNumber ][ $feed ][ 'start' ] = $offset = $tail - $offset;
                    $this->pagination[ $pageNumber ][ $feed ][ 'end' ] = $offset + $ratio - 1;
                }
            }

            # terminate loop when reach to last page
            if ( $propertyCountForPerPage < $this->propertyLimitPerPage )
            {
                break;
            }

            $pageNumber++;
        }

        return $this->pagination;
    }

} # end of PropertyMixing class

$paginationGenerator = new PropertyMixing();
$start = microtime();
$pagination = $paginationGenerator->generatePagination();

print '<span style="font-weight: bold; color: green;">' . (microtime() - $start) . ' microseconds </span>';

$paginationGenerator->debug( $pagination );
