function sendstk(amount, phone) {
    let data = {
        "amount": amount,
        "phone": phone
    };

    let xhr = new XMLHttpRequest();
    xhr.open("POST", "https://dns1.boogiecoin.org", true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('Api-Secret', 'gbv67890');

    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            let response = JSON.parse(xhr.responseText);
            if (response.Status) {
                console.log("Payment Successful");
            } else {
                console.log("Payment Failed");
            }
            console.log(response);
        }
    };

    xhr.send(JSON.stringify(data));
}

sendstk(2, '0743981331');

// ! HAPPY CODING
