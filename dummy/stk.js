//  SAFARICOM PAYMENT INTEGRATION ON A WEBSITE

//   Use this endpoint API to send the STK push notification
//   amount: The amount to be sent
//   phone: The recipient's phone number
//   Example:
//   sendStk(1, "07123456789");
//   You'll receive a response fom safaricom if the payament was successfull or not 
//   If you have your own Till/Bank/Paybill then slide => +254743981331

function sendstk(amount,phone){
    let data = {
        "amount": amount,
        "phone": phone
    };

    fetch("https://api.boogiecoin.org",{
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Api-Secret': 'gbv67890'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if(data.Status){
            console.log("Payment Successful")
        }else{
            console.log("Payment Failed")
        }
        console.log(data)
    })
}
sendstk(2,'07123456789')

// ! HAPPY CODING

