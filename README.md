## Postmates API integration 

This extension allows for the creation of a 'Postmates' delivery zone, real-time api delivery quotes during cart view, and a button to call a Postmates driver for each order. 

### Installation

Download the extension and add to your extensions folder under "/extensions/cupnoodles/postmates" inside your Tasty Igniter install.
Enable extension in Systems -> Extensions.


### API keys setup

Both sandbox and production API keys, as well as customer ID, must be entered into the Postmates API Settings fields (System > Settings > Postmates API Settings).

You'l need to sign up for developer credentials at https://partner.postmates.com/welcome-api in order to get access to sandbox and productoin API keys. 

### Delivery Zone setup

In Restaurant > Locations > Your location > Delivery, create a new Delivery Area with your desired shape, and the 'Delivery Service' setting set to 'Postmates'. All other setting are applied as normal - the 'Charge' amount will be added to the Postmates delivery quote if it's non-0. You cannot use negative values here, since they'll be interpreted as a delivery zone exclusion.

### Setup Notes

Because this extension changes the behavior of some elements of the `Igniter\Local::LocalBox` component, it's highly recommended that the following language definitions are updated to reflect how your store is interacting with Postmates.

```
igniter.local::default.text_condition_above_total
igniter.local::default.text_condition_all_orders
igniter.local::default.text_condition_below_total
igniter.local::default.text_delivery
```

Additionally, the postmates API is called with a full address from the localBox component. Since the API will fail unless it has a full address, you'll need to update 

```
igniter.local::default.label_search_query
```

to specifically state that a full address needs to be entered (not just a postcode).

Since a phone number is required to make Postmates deliveries, you must make telephone a required field for any delivery order. 


### TODO

Postmates API returns a `quote_id` field so that an order can be locked into the price it was quoted at (not yet implemented). 
