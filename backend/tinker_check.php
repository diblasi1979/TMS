$o = App\Models\DeliveryOrder::where('reference_number', 'LIKE', '%2525%')->orWhere('id', 2525)->first();
if($o) { echo 'Order: id='.$o->id.' ref='.$o->reference_number.' status='.$o->status.' route_id='.($o->route_id ?? 'null').PHP_EOL; } else { echo 'Not found by 2525'.PHP_EOL; }
echo 'Total orders: '.App\Models\DeliveryOrder::count().PHP_EOL;
echo 'Pending orders: '.App\Models\DeliveryOrder::where('status','pending')->count().PHP_EOL;
$orders = App\Models\DeliveryOrder::where('status','pending')->take(3)->get(['id','reference_number','delivery_address','status']);
foreach($orders as $od) { echo 'id='.$od->id.' ref='.$od->reference_number.' addr='.$od->delivery_address.PHP_EOL; }
