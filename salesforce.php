<?php 
//Theme Init
require_once("inc/init.php");

//SalesForce Includes
define("SOAP_CLIENT_BASEDIR", "inc/salesforce/soapclient");
require_once (SOAP_CLIENT_BASEDIR.'/SforceEnterpriseClient.php');
require_once ('inc/salesforce/userAuth.php');

//Output arrays in a pretty format
function arraywrapper($array){
	echo "<pre>";
	print_r($array);
	echo "</pre>";
}
		
date_default_timezone_set('America/Denver'); //Set default timezone
		
$startdate = date('c', '1/1/2011'); // 1/1/2011 date in unix time format and sets as default start date.
$enddate = date('c'); //gets current date in unix time format and sets as default end date.
$alltime = true;

if(	isset($_GET['start'])
	&& 	isset($_GET['end'])
	&& 	!empty ($_GET['start'])
	&& 	!empty($_GET['end'])
	){
		$startdate = date('c', strtotime($_GET['start']));
		$enddate = date('c', strtotime($_GET['end']."+1day"));
		$alltime = false;
} 

							  
//Creation Connection to SF Database
$mySforceConnection = new SforceEnterpriseClient();
$mySoapClient = $mySforceConnection->createConnection(SOAP_CLIENT_BASEDIR.'/enterprise.wsdl.xml');
$mylogin = $mySforceConnection->login($USERNAME, $PASSWORD);


/////////////////  Leads Query /////////////////

$leadsquery = "SELECT LeadSource, IsConverted, CreatedDate, ConvertedOpportunity.Id, ConvertedOpportunity.CreatedDate, ConvertedOpportunity.Name, ConvertedOpportunity.StageName FROM Lead WHERE CreatedDate >= ".$startdate." AND CreatedDate <= ".$enddate." ";
$leadsresponse = $mySforceConnection->query(($leadsquery));
!$leaddone = false;

//Setting up the arrays.
$responseConverted = array();
$responseUnconverted = array();
$responseConvertedOpportunities = array();
$responseConvertedOpportunitiesWon = array();

//Get the ENTIRE array by running the query a few times using queryMore().
  if ($leadsresponse->size > 0) {
    while (!$leaddone) {
      foreach ($leadsresponse->records as $record) {
			if($record->IsConverted){
				$responseConverted[] = $record->LeadSource; //Write converted leads to one array
			} else {
				$responseUnconverted[] = $record->LeadSource; //Write unconverted leads to another array
			}
			if($record->ConvertedOpportunity){
				$responseConvertedOpportunities[] = $record->ConvertedOpportunity;				
			}
			if($record->ConvertedOpportunity->StageName == "Closed Won"){
				$responseConvertedOpportunitiesWon[] = $record->ConvertedOpportunity;				
			}
      }
      if ($leadsresponse->done != true) {
        try {
          $leadsresponse = $mySforceConnection->queryMore($leadsresponse->queryLocator);
        } catch (Exception $e) {
          print_r($mySforceConnection->getLastRequest());
          echo $e->faultstring;
        }
      } else {
        $leaddone = true;
      }
    }
  }

	//Takes converted list and changes NULL values to "Uncategorized".
	$responseConverted = array_map( function( $v ){ return ( is_null($v) ) ? "Uncategorized" : $v; } , $responseConverted);

	//Takes unconverted list and changes NULL values to "Uncategorized".
	$responseUnconverted = array_map( function( $v ){ return ( is_null($v) ) ? "Uncategorized" : $v; } , $responseUnconverted);

	// Merge the two leads arrays (converted and unconverted), then count the values and give us a simple $key => $value array where $key is the lead source, and $value is the total count. 
	$leadList = array_count_values(array_merge($responseConverted, $responseUnconverted)); 
	$converedList = array_count_values($responseConverted);

/////////////////  Opportunities Query /////////////////

$oppquery = "SELECT LeadSource, StageName, Id FROM Opportunity WHERE CreatedDate >= ".$startdate." AND CreatedDate <= ".$enddate." ";
$oppresponse = $mySforceConnection->query(($oppquery));

//Setting up the arrays.
$responseOppLeadSource = array();
$responseOppWon = array();

foreach ($oppresponse->records as $record) {
		$responseOppLeadSource[] = $record->LeadSource;
		if($record->StageName == 'Closed Won'){
			$responseOppWon[] = $record->LeadSource;
		}
}


	// Takes list and changes NULL values to "Uncategorized".
	$responseOppLeadSource = array_map( function( $v ){ return ( is_null($v) ) ? "Uncategorized" : $v; } , $responseOppLeadSource);

	// Merge the two arrays then count the values and give us a simple $key => $value array where $key is the lead source, and $value is the total count. 
	$responseOppLeadSource = array_count_values($responseOppLeadSource);

	// Takes list and changes NULL values to "Uncategorized".
	$responseOppWon = array_map( function( $v ){ return ( is_null($v) ) ? "Uncategorized" : $v; } , $responseOppWon);

	// Merge the two arrays then count the values and give us a simple $key => $value array where $key is the lead source, and $value is the total count. 
	$responseOppWon = array_count_values($responseOppWon);

	$OppWonTotal = array_sum($responseOppWon);


$sourceList = array_keys(array_merge($leadList, $responseOppWon, $responseOppLeadSource));
?>

<div class="row">
	<div class="col-xs-12 col-sm-7 col-md-7 col-lg-4">
		<h1 class="page-title txt-color-blueDark">
			<i class="fa fa-table fa-fw "></i> 
				Salesforce 
			<span>> 
				Sales Statistics
			</span>
		</h1>
	</div>
	<div id="reportrange" class="pull-right" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; margin-right: 14px; ">
	  <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>
	  <span>Date Picker</span> <b class="caret"></b>
	</div>	
</div>



<!-- widget grid -->
<section id="widget-grid" class="">
	<!-- row -->
	<div class="row">

		<!-- NEW WIDGET START -->
		<article class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

			<!-- Widget ID (each widget will need unique ID)-->
			<div class="jarviswidget jarviswidget-color-blueDark" id="wid-id-3" data-widget-editbutton="false">
			
				<header>
					<span class="widget-icon"> <i class="fa fa-table"></i> </span>
					<h2>
					<?php
						if ($alltime){
							echo "Showing Data from <strong>ALL TIME</strong> &nbsp;&nbsp;<small><em>(".date("F d, Y", strtotime($startdate))." to ".date("F d, Y", strtotime($enddate)).")</em></small>";
						} else {
							echo "Showing Data from <strong>".date("F d, Y", strtotime($startdate))."</strong> to <strong>".date("F d, Y", strtotime($enddate))."</strong>";
						}
					?>
					</h2>

				</header>

				<!-- widget div-->
				<div>

					<!-- widget edit box -->
					<div class="jarviswidget-editbox">
						<!-- This area used as dropdown edit box -->

					</div>
					<!-- end widget edit box -->

					<!-- widget content -->
					<div class="widget-body no-padding">

						<table id="datatable_tabletools" class="table table-striped table-bordered table-hover" width="100%">
							<thead>
								<tr>
									<th data-hide="expand">Lead Source</th>
									<th>Leads</th>
									<th data-hide="phone,tablet">Opportunities</th>
									<th data-hide="phone,tablet">Leads to Opportunities</th>
									<th data-hide="phone,tablet">Opportunities Won</th>
									<th data-hide="phone,tablet">Opportunities Conversion</th>
								</tr>
							</thead>
							<tbody>
							<?php
								foreach($sourceList as $key => $leadsource){
							?>  
								 <tr>
									<td><?php echo $leadsource; ?></td>
									<td>
										<?php 
											echo $leadList[$leadsource];
											if($converedList[$leadsource] != ""){ echo" <small style='color:#ccc;font-style:italic;'>(".$converedList[$leadsource].")</small>"; } 
										?>
									</td>
									<td><?php echo $responseOppLeadSource[$leadsource]; ?></td>
									<td><?php
										$conversionPercentage = ($leadList[$leadsource] != 0 ? round(((($responseOppLeadSource[$leadsource])/($leadList[$leadsource]))*100), 2) : 0);
										echo ($conversionPercentage < 100 ? $conversionPercentage . "%" : "100%" );  ?></td>
									<td><?php echo $responseOppWon[$leadsource]; ?></td>
									<td><?php echo ($responseOppWon[$leadsource] != 0 ? round(((($responseOppWon[$leadsource])/($responseOppLeadSource[$leadsource]))*100), 2)."%" : "0%"); ?></td>
								</tr> 
								<?php
							} ?>
								 <tr>
									<td><strong>TOTAL</strong></td>
									<td><strong><?php echo $leadsresponse->size; ?></strong></td>
									<td><strong><?php echo $oppresponse->size; ?></strong></td>
									<td><strong><?php echo ($oppresponse->size != 0 ? round(((($oppresponse->size)/($leadsresponse->size))*100), 2)."%" : "0%"); ?></strong></td>
									<td><strong><?php echo $OppWonTotal; ?></strong></td>
									<td><strong><?php echo ($OppWonTotal != 0 ? round(((($OppWonTotal)/($oppresponse->size))*100), 2)."%" : "0%"); ?></strong></td>
								</tr> 									
							</tbody>
						</table>

					</div>
					<!-- end widget content -->

				</div>
				<!-- end widget div -->

			</div>
			<!-- end widget -->

		</article>
		<!-- WIDGET END -->

	</div>

	<!-- end row -->

	<!-- end row -->

</section>
<!-- end widget grid -->

<script type="text/javascript">

	pageSetUp();
		
	// PAGE RELATED SCRIPTS
	
	// pagefunction	
	var pagefunction = function() {
		
		
		/* BASIC ;*/
			var responsiveHelper_dt_basic = undefined;
			var responsiveHelper_datatable_fixed_column = undefined;
			var responsiveHelper_datatable_col_reorder = undefined;
			var responsiveHelper_datatable_tabletools = undefined;
			
			var breakpointDefinition = {
				tablet : 1024,
				phone : 480
			};

			var startDate;
			var endDate;

			//Date Picker
			$(document).ready(function() {

              var cb = function(start, end, label) {
                console.log(start.toISOString(), end.toISOString(), label);
                $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                window.location.href = "/#ajax/salesforce.php?start=" + start.format('YYYY-MM-DD') +"&end="+end.format('YYYY-MM-DD');
              }

              var optionSet1 = {
                startDate: '01/01/2011',
                endDate: moment(),
                minDate: '01/01/2011',
                maxDate: moment(),
                showDropdowns: true,
                showWeekNumbers: true,
                timePicker: false,
                timePickerIncrement: 1,
                timePicker12Hour: true,
                ranges: {
                   'All Time': ['01/01/2011', moment()],
                   'Today': [moment(), moment()],
                   'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                opens: 'left',
                buttonClasses: ['btn btn-default'],
                applyClass: 'btn-small btn-primary',
                cancelClass: 'btn-small',
                format: 'MM/DD/YYYY',
                separator: ' to ',
                locale: {
                    applyLabel: 'Submit',
                    cancelLabel: 'Clear',
                    fromLabel: 'From',
                    toLabel: 'To',
                    customRangeLabel: 'Custom',
                    daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr','Sa'],
                    monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    firstDay: 1
                }
              };

              $('#reportrange span').html(moment().subtract(29, 'days').format('MMMM D, YYYY') + ' - ' + moment().format('MMMM D, YYYY'));

              $('#reportrange').daterangepicker(optionSet1, cb);

              $('#reportrange').data('daterangepicker').setOptions(optionSet1, cb); //Display Date Picker


           });


		//END DATE PICKER

			$('#dt_basic').dataTable({
				"sDom": "<'dt-toolbar'<'col-xs-12 col-sm-6'f><'col-sm-6 col-xs-12 hidden-xs'l>r>"+
					"t"+
					"<'dt-toolbar-footer'<'col-sm-6 col-xs-12 hidden-xs'i><'col-xs-12 col-sm-6'p>>",
				"autoWidth" : true,
				"preDrawCallback" : function() {
					// Initialize the responsive datatables helper once.
					if (!responsiveHelper_dt_basic) {
						responsiveHelper_dt_basic = new ResponsiveDatatablesHelper($('#dt_basic'), breakpointDefinition);
					}
				},
				"rowCallback" : function(nRow) {
					responsiveHelper_dt_basic.createExpandIcon(nRow);
				},
				"drawCallback" : function(oSettings) {
					responsiveHelper_dt_basic.respond();
				}
			});

		/* END BASIC */
		
		/* COLUMN FILTER  */
	    var otable = $('#datatable_fixed_column').DataTable({
	    	//"bFilter": false,
	    	//"bInfo": false,
	    	//"bLengthChange": false
	    	//"bAutoWidth": false,
	    	//"bPaginate": false,
	    	//"bStateSave": true // saves sort state using localStorage
			"sDom": "<'dt-toolbar'<'col-xs-12 col-sm-6 hidden-xs'f><'col-sm-6 col-xs-12 hidden-xs'<'toolbar'>>r>"+
					"t"+
					"<'dt-toolbar-footer'<'col-sm-6 col-xs-12 hidden-xs'i><'col-xs-12 col-sm-6'p>>",
			"autoWidth" : true,
			"preDrawCallback" : function() {
				// Initialize the responsive datatables helper once.
				if (!responsiveHelper_datatable_fixed_column) {
					responsiveHelper_datatable_fixed_column = new ResponsiveDatatablesHelper($('#datatable_fixed_column'), breakpointDefinition);
				}
			},
			"rowCallback" : function(nRow) {
				responsiveHelper_datatable_fixed_column.createExpandIcon(nRow);
			},
			"drawCallback" : function(oSettings) {
				responsiveHelper_datatable_fixed_column.respond();
			}		
		
	    });
	    
	    // custom toolbar
	    $("div.toolbar").html('<div class="text-right"><img src="img/logo.png" alt="SmartAdmin" style="width: 111px; margin-top: 3px; margin-right: 10px;"></div>');
	    	   
	    // Apply the filter
	    $("#datatable_fixed_column thead th input[type=text]").on( 'keyup change', function () {
	    	
	        otable
	            .column( $(this).parent().index()+':visible' )
	            .search( this.value )
	            .draw();
	            
	    } );
	    /* END COLUMN FILTER */   
    
		/* COLUMN SHOW - HIDE */
		$('#datatable_col_reorder').dataTable({
			"sDom": "<'dt-toolbar'<'col-xs-12 col-sm-6'f><'col-sm-6 col-xs-6 hidden-xs'C>r>"+
					"t"+
					"<'dt-toolbar-footer'<'col-sm-6 col-xs-12 hidden-xs'i><'col-sm-6 col-xs-12'p>>",
			"autoWidth" : true,
			"preDrawCallback" : function() {
				// Initialize the responsive datatables helper once.
				if (!responsiveHelper_datatable_col_reorder) {
					responsiveHelper_datatable_col_reorder = new ResponsiveDatatablesHelper($('#datatable_col_reorder'), breakpointDefinition);
				}
			},
			"rowCallback" : function(nRow) {
				responsiveHelper_datatable_col_reorder.createExpandIcon(nRow);
			},
			"drawCallback" : function(oSettings) {
				responsiveHelper_datatable_col_reorder.respond();
			}			
		});
		
		/* END COLUMN SHOW - HIDE */

		/* TABLETOOLS */
		$('#datatable_tabletools').dataTable({
			"pageLength":50,
			// Tabletools options: 
			//   https://datatables.net/extensions/tabletools/button_options
			"sDom": "<'dt-toolbar'<'col-xs-12 col-sm-6'f><'col-sm-6 col-xs-6 hidden-xs'T>r>"+
					"t"+
					"<'dt-toolbar-footer'<'col-sm-6 col-xs-12 hidden-xs'i><'col-sm-6 col-xs-12'p>>",
	        "oTableTools": {
	        	 "aButtons": [
	             "copy",
	             "csv",
	             "xls",
	                {
	                    "sExtends": "pdf",
	                    "sTitle": "LoneTreeUSA - Sales Force Report",
	                    "sPdfMessage": "Showing Data from:"+startDate, 
	                    "sPdfSize": "letter"
	                },
	             	{
                    	"sExtends": "print",
                    	"sMessage": "<img src='/ltlogo.png'>"
                	}
	             ],
	            "sSwfPath": "js/plugin/datatables/swf/copy_csv_xls_pdf.swf"
	        },
			"autoWidth" : true,
			"preDrawCallback" : function() {
				// Initialize the responsive datatables helper once.
				if (!responsiveHelper_datatable_tabletools) {
					responsiveHelper_datatable_tabletools = new ResponsiveDatatablesHelper($('#datatable_tabletools'), breakpointDefinition);
				}
			},
			"rowCallback" : function(nRow) {
				responsiveHelper_datatable_tabletools.createExpandIcon(nRow);
			},
			"drawCallback" : function(oSettings) {
				responsiveHelper_datatable_tabletools.respond();
			}
		});
		
		/* END TABLETOOLS */

	};

	// load related plugins
	
	loadScript("js/plugin/datatables/jquery.dataTables.min.js", function(){
		loadScript("js/plugin/datatables/dataTables.colVis.min.js", function(){
			loadScript("js/plugin/datatables/dataTables.tableTools.min.js", function(){
				loadScript("js/plugin/datatables/dataTables.bootstrap.min.js", function(){
					loadScript("js/plugin/datatable-responsive/datatables.responsive.min.js", pagefunction)
				});
			});
		});
	});


</script>
