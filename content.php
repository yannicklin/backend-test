<?php
/* Created By Yannick,
	Date: 2016-04-13
	Function: Make a PHP function with parameters to execute XML queryStay
	Input:
		Query_DateRange: the coming days of room availity, default is 21 days
		Query_minNights: the minimum Nights for stay, default is 1 night
	Output:
		raw HTML string
*/

function queryStays($Query_DateRange = 21, $Query_minNights = 1) {

	// Set default TimeZone as Australia Brisbane UTC+10
	date_default_timezone_set('Australia/Brisbane');

	// Set Date time format to get string like 2016-04-26
    $strDateTimeFormat = "Y-m-d";

	// Basic Configurations
    $strBaseURI = "https://app-apac.thebookingbutton.com";
    $strFileFormat = "";
    $strChannelCode = "camhotsyddirect";
    $objDateTime = new DateTime('NOW');
    $strStartDate = $objDateTime->format($strDateTimeFormat);
    $strEndDate = $objDateTime->add(new DateInterval('P' . $Query_DateRange . 'D'))->format($strDateTimeFormat);

	// Compose the Query URI, it would be like "https://app-apac.thebookingbutton.com/api/v1/properties/camhotsyddirect/rates?start_date=2012-06-15"
    $strPropertyURI = $strBaseURI . "/api/v1/properties/" . $strChannelCode . "/rates" . $strFileFormat ."?start_date=" . $strStartDate . "&end_date=" . $strEndDate;

	// Use PHP SimpleXML to load the file
    $RoomRates = simplexml_load_file($strPropertyURI) or die("feed not loading");

	// Set default output array in final: arrProperty
    $arrProperty = array();
    foreach ($RoomRates->property as $Property) {
	
		// Set a temporarily output array: arrRoomType as the container to store the Date-Rate information
        $arrRoomType = array();
        foreach ($Property->{'room-types'}->{'room-type'} as $RoomType) {
		
			// Set a temporarily output array: arrRoomDateRate
            $arrRoomDateRate = array();
			
			// Init the avaiable Nights with Counter and Rates Accumulation
            $accAvailableNights = 0;
            $arrAccumulatedRates = array_fill(0, $Query_minNights, 0);
			
            foreach ($RoomType->{'room-type-dates'}->{'room-type-date'} as $RoomTypeDate) {

                // Rolling the Accumulation;
				// for example, for the consecutive 3 night to the final checkout date of 2016-04-12, we sum up the rate of 2016-04-10 (which value in $arrAccumulatedRates[0]), the rate of 2016-04-11 ($arrAccumulatedRates[1]), and the rate of 2016-04-12 $arrAccumulatedRates[2]); for the next night, we find the 2016-04-13 is available, so we need to sum up the night started from 2016-04-11 to 2016-04-13. The mechanism used here is while codes execution here would put the "arr[i] = arr[i-1]". That is, for the record of 2016-04-13, the value of $arrAccumulatedRates[1] is the rate of 2016-04-12, not the old 2016-04-11 stored while calculating 2016-04-12 night
                for($h=1; $h < $Query_minNights; $h++){
                    $arrAccumulatedRates[$h-1] = $arrAccumulatedRates[$h];
                }
                $arrAccumulatedRates[$Query_minNights-1] = (int)$RoomTypeDate->rate;

				// Check the night is available; if not, no result stored in final, and also reset the counter of accumulated Available Nights
                if($RoomTypeDate->available > 0) {
                    $accAvailableNights ++;

					// checke the accumulated nights whether fit the User request; if yes, put the accurate accumulated rates into record; else put zero as accumulation
                    if ( $accAvailableNights == $Query_minNights ) {
                        array_push($arrRoomDateRate, array("Date" => (string)$RoomTypeDate->date, "Rate" => (int)$RoomTypeDate->rate, "accRates" => array_sum($arrAccumulatedRates)));
                        $accAvailableNights --;
                    }else {
                        array_push($arrRoomDateRate, array("Date" => (string)$RoomTypeDate->date, "Rate" => (int)$RoomTypeDate->rate, "accRates" => 0));
                    }
                }else {
                    $accAvailableNights = 0;
                }
            }
			
            if (!empty($arrRoomDateRate)) {
                // if there is no available nights meet user query, put zero into minPrice; else, put the minimum price store in the temporary object arrRoomDateRate
				// Put the temporary array objects (arrRoomDateRate) into the "Dates" as sub-array
				if (count(array_diff(array_column($arrRoomDateRate, "accRates"), array(0)))) {
                    array_push($arrRoomType, array("RoomType" => (string)$RoomType->name, "minPrice" => min(array_diff(array_column($arrRoomDateRate, "accRates"), array(0))), "Dates" => $arrRoomDateRate));
                }else{
                    array_push($arrRoomType, array("RoomType" => (string)$RoomType->name, "minPrice" => 0, "Dates" => $arrRoomDateRate));
                }
            }else{
				// if there is no any available single nights, put empty array in the "Dates"
                array_push($arrRoomType, array("RoomType" => (string)$RoomType->name, "minPrice" => 0, "Dates" => []));
            }
        }
        if (!empty($arrRoomType)) {
			// To see whether there is any room type could meet the user query for minimum nights stay.
			// Put the temporary array objects (arrRoomDateRate) into the "RoomTypes" as sub-array
            if (count(array_diff(array_column($arrRoomType, "minPrice"), array(0)))) {
                array_push($arrProperty, array("Property" => (string)$Property->name, "minPrice" => min(array_diff(array_column($arrRoomType, "minPrice"), array(0))), "RoomTypes" => $arrRoomType));
            }else{
                array_push($arrRoomType, array("Property" => (string)$Property->name, "minPrice" => 0, "RoomTypes" => $arrRoomType));
            }
        }
    }

    /* Compose the final output HTML */
    $strHTML = "";
	// if there is no any room type has single available night
    if (empty($arrProperty)){
        $strHTML = "<h1>Sorry! No any available rooms under your choice. The date range from " . $strStartDate . " to " . $strEndDate . ", with condition of min. " . $Query_minNights . " Night(s) stay.</h1>";
    }else{
		// Compose the conditions
        $strHTML = "<h1> According to your choice, the Date Range from " . $strStartDate . " to " . $strEndDate . ", with condition of min. " . $Query_minNights . " Night(s) stay, the best price would be: </h1>";
		
        for($i = 0; $i < count($arrProperty); $i++) {
			// store the overall minimum rate in the view of Property
            $minPrice_Property = $arrProperty[$i]["minPrice"];
            $strHTML .= "<h2>Property - " . $arrProperty[$i]["Property"] . ", minimum $" . $minPrice_Property . "</h2>";
            
			// if the minPrice is zero, means there is no suitable nights under user query
			if ($minPrice_Property==0) {$strHTML .= " <span style='font-weight: 500; color: blue;'> !! No available night(s) in this Property !! </span>";}
            $strHTML .= "</h2>";
			
			// Do the iteration to parse the array in RoomType
            $arrayRoomType_T = $arrProperty[$i]["RoomTypes"];
            for($j = 0; $j < count($arrayRoomType_T); $j++) {
				// store the overall minimum rate in the view of RoomType
                $minPrice_RoomType = $arrayRoomType_T[$j]["minPrice"];
                $strHTML .= "<h3>Room Type - " . $arrayRoomType_T[$j]["RoomType"] . ", minimum $" . $minPrice_RoomType;
				
				// if the minPrice is zero, means there is no suitable nights under user query
                if ($minPrice_RoomType==0) {$strHTML .= " <span style='font-weight: 500; color: blue;'> !! No available night(s) in this Room Type !! </span>";}
                $strHTML .= "</h3>";
				
				// Do the iteration to parse the array in Date Rates
                $arrayRoomDateRate_T = $arrayRoomType_T[$j]["Dates"];
                for($k = 0; $k < count($arrayRoomDateRate_T); $k++) {
                    $strHTML .= "Date - " . $arrayRoomDateRate_T[$k]["Date"] . ", $" . $arrayRoomDateRate_T[$k]["Rate"];

                    if ($Query_minNights > 1) {
						// if the value in accRates is not zero and the minimum nights user queried is more than one night, then this record should be one the possible checkout date 
                        if ($arrayRoomDateRate_T[$k]["accRates"] > 0) {
                            $strHTML .= ", total Rent$" . $arrayRoomDateRate_T[$k]["accRates"] . " check-in from " . date($strDateTimeFormat, strtotime(("-" . $Query_minNights . " day"), strtotime($arrayRoomDateRate_T[$k]["Date"])));

							// inidicate whether the price meets the lowest, from the view of property or room type
                            if ($arrayRoomDateRate_T[$k]["accRates"] == $minPrice_Property){
                                $strHTML .= "<span style='font-weight: 700; color: red;'> !! Best Deal in this Property !! </span>";
                            } else if ($arrayRoomDateRate_T[$k]["accRates"] == $minPrice_RoomType) {
                                $strHTML .= "<span style='font-weight: 500; color: green;'> !! Cheapest Date in this Room Type !! </span>";
                            }
                        }
                    }else {
						// if User only query one night stay, then simply compare the minimum on Rate; inidicate whether the price meets the lowest, from the view of property or room type
                        if ($arrayRoomDateRate_T[$k]["accRates"] == $minPrice_Property){
                            $strHTML .= "<span style='font-weight: 700; color: red;'> !! Best Deal in this Property !! </span>";
                        } else if ($arrayRoomDateRate_T[$k]["accRates"] == $minPrice_RoomType) {
                            $strHTML .= "<span style='font-weight: 500; color: green;'> !! Cheapest Date in this Room Type !! </span>";
                        }
                    }
					// Make each record present in one line.
                    $strHTML .= "<br />";
                }
            }
        }
    }
    return $strHTML;
}
?>
<form method="post">
    Date: <input type="number" min="7" max="60" name="dateRange" value="14"><br>
    Minimum Nights Stay: <input type="number" min="1" max="5" name="NightsStay" value="1"><br>
    <input type="submit">
</form>
<?php
// Form Input and Call Function
if($_POST["dateRange"] && $_POST["NightsStay"]) {
    echo queryStays($_POST["dateRange"], $_POST["NightsStay"]);
}
