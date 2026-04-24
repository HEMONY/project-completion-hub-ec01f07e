// // save-kyc.js
// document.addEventListener('DOMContentLoaded', function() {
//     const form = document.getElementById('kyc-form');
//     const nextStepBtn = document.getElementById('next-step-btn');
    
//     if (nextStepBtn) {
//         nextStepBtn.addEventListener('click', function(e) {
//             e.preventDefault();
//             saveKYCData();
//         });
//     }
// });

// function saveKYCData() {
//     const form = document.getElementById('kyc-form');
//     const formData = new FormData(form);
    
//     // Add additional validation data
//     formData.append('save_kyc', 'true');
    
//     // Show loading state
//     const nextStepBtn = document.getElementById('next-step-btn');
//     if (nextStepBtn) {
//         const originalText = nextStepBtn.textContent;
//         nextStepBtn.textContent = 'Saving...';
//         nextStepBtn.disabled = true;
//     }
    
//     // Submit via AJAX
//     fetch('../../../controller/steps/kyc/save_kyc.php', {
//         method: 'POST',
//         body: formData
//     })
//     .then(response => response.json())
//     .then(data => {
//         if (data.success) {
//             // Show success message
//             alert(data.message || 'KYC data saved successfully!');
            
//             // Store entity info in sessionStorage for the next page
//             if (data.entity_id) {
//                 sessionStorage.setItem('current_entity_id', data.entity_id);
//                 sessionStorage.setItem('current_entity_name', data.entity_name);
//                 sessionStorage.setItem('engagement_number', data.engagement_number);
//             }
            
//             // Redirect to next step
//             if (data.redirect_url) {
//                 window.location.href = data.redirect_url;
//             }
//         } else {
//             // Show errors
//             let errorMessage = data.message || 'Failed to save KYC data.';
//             if (data.errors && data.errors.length > 0) {
//                 errorMessage += '\n\nErrors:\n' + data.errors.join('\n');
//             }
//             alert(errorMessage);
            
//             // Restore button state
//             if (nextStepBtn) {
//                 nextStepBtn.textContent = originalText;
//                 nextStepBtn.disabled = false;
//             }
//         }
//     })
//     .catch(error => {
//         console.error('Error:', error);
//         alert('An error occurred while saving. Please try again.');
        
//         // Restore button state
//         if (nextStepBtn) {
//             nextStepBtn.textContent = originalText;
//             nextStepBtn.disabled = false;
//         }
//     });
// }

// // Function to save form data to session via AJAX (for interim saves)
// function saveToSession() {
//     const form = document.getElementById('kyc-form');
//     const formData = new FormData(form);
    
//     // Add flag to indicate this is an interim save
//     formData.append('interim_save', 'true');
    
//     fetch('', {
//         method: 'POST',
//         body: formData
//     })
//     .then(response => response.json())
//     .then(data => {
//         if (data.success) {
//             console.log('Data saved to session');
//         }
//     })
//     .catch(error => {
//         console.error('Error saving to session:', error);
//     });
// }

// // Auto-save when leaving the page
// window.addEventListener('beforeunload', function(e) {
//     // You might want to save form data here
//     // saveToSession();
// });

// // Periodically save form data (every 30 seconds)
// setInterval(() => {
//     // saveToSession();
// }, 30000);