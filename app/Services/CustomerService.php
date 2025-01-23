<?php
namespace App\Services;
use App\Models\Customers;

class CustomerService
{
    public function store($custId, $custName, $description,$avatar, $platformRef) : Customers
    {
        $customer = new Customers();
        $customer->custId = $custId;
        $customer->custName = $custName;
        $customer->avatar = $avatar;
        $customer->description = $description;
        $customer->platformRef = $platformRef;
        $customer->save();
        return $customer;
    }
}
