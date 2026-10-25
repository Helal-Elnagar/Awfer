<!-- FireBase -->
<!-- The core Firebase JS SDK is always required and must be listed first -->
{{-- <script src="https://www.gstatic.com/firebasejs/7.6.1/firebase.js"></script> --}}
<script src="https://www.gstatic.com/firebasejs/7.6.1/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.6.1/firebase-messaging.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.6.1/firebase-analytics.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.6.1/firebase-auth.js"></script>
<script src="https://www.gstatic.com/firebasejs/7.6.1/firebase-firestore.js"></script>


<script>
    // Your web app's Firebase configuration
    // TODO: change this configuration
    const firebaseConfig = {
     apiKey: "AIzaSyCwCY7_zgsj1WCLk0RtZ4PqJIEepmKsFYU",
  authDomain: "test-33d57.firebaseapp.com",
  projectId: "test-33d57",
  storageBucket: "test-33d57.firebasestorage.app",
  messagingSenderId: "99905345620",
  appId: "1:99905345620:web:30880fa5dc984a41e835c1",
  measurementId: "G-22FBZEZXQN"
    };

    // Initialize Firebase
    firebase.initializeApp(firebaseConfig);
    //firebase.analytics();

    const messaging = firebase.messaging();
    window.fcmMessageing = messaging;

    messaging.usePublicVapidKey("BGhzg-qcJ9pr0Et6t6nl4E5ia2WrrE3RbItTbX97uY5d-2lOajCX5qkYdngyCkSj9fRpa-bmCj_KHCRP6fx7YPM");

    Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
            console.log('Notification permission granted.');
        } else {
            console.log('Unable to get permission to notify.');
        }
    });

    messaging.getToken().then((currentToken) => {
        if (currentToken) {
            console.log(currentToken);
            localStorage.setItem('device_id', currentToken);
            const deviceIdInput = document.getElementById('device_id');
            if (deviceIdInput) {
                deviceIdInput.value = localStorage.getItem('device_id');
            }
        } else {
            console.log('No Instance ID token available. Request permission to generate one.');
        }
    }).catch((err) => {
        console.log('An error occurred while retrieving token. ', err);
    });

    messaging.onMessage(function(payload) {
        console.log('Message received. ', payload);
        
        // Extract title and body from either notification or data payload
        let title = payload.notification?.title || payload.data?.title || 'إشعار جديد';
        let body = payload.notification?.body || payload.data?.body || payload.data?.body_ar || 'لديك رسالة جديدة';
        
        // Show browser notification for foreground messages
        if (Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: body,
                icon: '/favicon.ico',
                tag: 'fcm-notification-' + Date.now(),
                requireInteraction: false,
                silent: false
            });
            
            notification.onclick = function() {
                window.focus();
                notification.close();
                // Handle notification click - could redirect to specific page
                if (payload.data?.url) {
                    window.location.href = payload.data.url;
                }
            };
        }
        
        // Also show alert as fallback
        alert('لديك رسالة جديدة : ' + body);
    });
</script>
