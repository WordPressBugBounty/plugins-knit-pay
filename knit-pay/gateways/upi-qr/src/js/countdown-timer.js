var targetTime;
var counterInterval;

// Update the countdown every second
function updateCountdown(countdownElement, timeFormat, callbackFunction) {
  const now = new Date();

  if (now.getTime() >= targetTime) {
    // Countdown has ended
    clearInterval(counterInterval);
    callbackFunction();
  } else {
    // Calculate remaining time
    const timeRemaining = targetTime - now.getTime();
    const hours = Math.floor(timeRemaining / (1000 * 60 * 60));
    const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);
    
    // Customize the countdown format based on user preference
    const formattedTime = timeFormat
      .replace("%hh", hours.toString().padStart(2, '0'))
      .replace("%mm", minutes.toString().padStart(2, '0'))
      .replace("%ss", seconds.toString().padStart(2, '0'))
      .replace("%h", hours)
      .replace("%m", minutes)
      .replace("%s", seconds);

    countdownElement.textContent = formattedTime;
  }
}

function knit_pay_countdown(durationInSeconds, elementID, timeFormat, callbackFunction){
	// Define your countdown parameters
	const countdownElement = document.getElementById(elementID); // Replace with your actual element ID
	
	targetTime = new Date().getTime() + durationInSeconds * 1000; // Calculate target time
		
	// Initial call to update the countdown
	updateCountdown(countdownElement, timeFormat, callbackFunction);
	
	// Update the countdown every second
	counterInterval = setInterval(() => updateCountdown(countdownElement, timeFormat, callbackFunction), 1000);
}
