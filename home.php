<?php
include 'includes/db.php';
session_start();

// Handle login when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Prepare the SQL statement to fetch the user
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username); // Bind the username parameter
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if the user exists
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify the password with bcrypt hash
        if (password_verify($password, $user['password'])) {
            // Start session and store user information
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to the dashboard page
            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Invalid password!";
        }
    } else {
        $error_message = "Invalid username!";
    }
    
    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harare City Council</title>
    <link rel="stylesheet" href="css/styles.css" />
    <style>
        /* Additional styles for modal error message */
        .error-message {
            color: red;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
      <div class="container">
        <div class="header-top">
          <div class="logo">
            <img src="images/kanzuru.PNG" alt="Harare City Council Logo" />
            <div class="logo-text">
              <h1>HARARE CITY COUNCIL</h1>
              <p>City of Sunshine</p>
            </div>
          </div>
          <button class="login-btn" onclick="openModal()">Staff Login</button>
        </div>
      </div>
      <nav>
        <div class="container">
          <ul class="nav-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="includes/about.php">About Us</a></li>
            <li><a href="includes/services.php">Services</a></li>
            <li><a href="includes/departments.php">Departments</a></li>
            <li><a href="includes/projects.php">Projects</a></li>
            <li><a href="news.html">News & Updates</a></li>
            <li><a href="contact.html">Contact Us</a></li>
          </ul>
        </div>
      </nav>
    </header>
    
    <!-- Hero Section -->
    <section class="hero">
      <div class="hero-content">
        <h2>Welcome to Harare City Council</h2>
        <p>
          Serving the residents of Harare with excellence and dedication.
          Together we build a sustainable, vibrant, and inclusive capital city.
        </p>
        <a href="#" class="cta-btn">Report an Issue</a>
      </div>
    </section>

    <!-- Services Section -->
    <section class="services">
      <div class="container">
        <h2 class="section-title">Our Services</h2>
        <div class="services-grid">
          <div class="service-card">
            <img src="/api/placeholder/400/200" alt="Water & Sanitation" />
            <div class="service-content">
              <h3>Water & Sanitation</h3>
              <p>
                Access information about water supply, billing, and sanitation
                services throughout the city.
              </p>
            </div>
          </div>
          <div class="service-card">
            <img src="/api/placeholder/400/200" alt="Waste Management" />
            <div class="service-content">
              <h3>Waste Management</h3>
              <p>
                Learn about waste collection schedules, recycling initiatives,
                and proper waste disposal.
              </p>
            </div>
          </div>
          <div class="service-card">
            <img src="/api/placeholder/400/200" alt="Business Licenses" />
            <div class="service-content">
              <h3>Business Licenses</h3>
              <p>
                Information on obtaining and renewing business licenses and
                permits within the city.
              </p>
            </div>
          </div>
          <div class="service-card">
            <img src="/api/placeholder/400/200" alt="Property & Rates" />
            <div class="service-content">
              <h3>Property & Rates</h3>
              <p>
                Details about property assessments, rate payments, and related
                municipal services.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- News Section -->
    <section class="news">
      <div class="container">
        <h2 class="section-title">Latest News & Updates</h2>
        <div class="news-grid">
          <div class="news-card">
            <img src="/api/placeholder/400/250" alt="News 1" />
            <div class="news-content">
              <div class="news-date">March 10, 2025</div>
              <h3>New Water Infrastructure Project Launched</h3>
              <p>
                The city council has launched a major water infrastructure
                upgrade project aimed at improving water supply reliability in
                eastern suburbs.
              </p>
              <a href="#" class="read-more">Read More</a>
            </div>
          </div>
          <div class="news-card">
            <img src="/api/placeholder/400/250" alt="News 2" />
            <div class="news-content">
              <div class="news-date">March 5, 2025</div>
              <h3>City Council Approves 2025 Budget</h3>
              <p>
                The Harare City Council has approved the 2025 municipal budget
                with focus on infrastructure development and service delivery.
              </p>
              <a href="#" class="read-more">Read More</a>
            </div>
          </div>
          <div class="news-card">
            <img src="/api/placeholder/400/250" alt="News 3" />
            <div class="news-content">
              <div class="news-date">February 25, 2025</div>
              <h3>Road Rehabilitation Program Extended</h3>
              <p>
                The ongoing road rehabilitation program has been extended to
                cover additional streets in Mbare and Highfield areas.
              </p>
              <a href="#" class="read-more">Read More</a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer>
      <div class="container">
        <div class="footer-content">
          <div class="footer-column">
            <h3>About HCC</h3>
            <ul>
              <li><a href="#">Mission & Vision</a></li>
              <li><a href="#">Council Members</a></li>
              <li><a href="#">Executive Team</a></li>
              <li><a href="#">History</a></li>
              <li><a href="#">Careers</a></li>
            </ul>
          </div>
          <div class="footer-column">
            <h3>Quick Links</h3>
            <ul>
              <li><a href="#">Pay Rates Online</a></li>
              <li><a href="#">Apply for Permits</a></li>
              <li><a href="#">Download Forms</a></li>
              <li><a href="#">Report Issues</a></li>
              <li><a href="#">Tenders & Procurement</a></li>
            </ul>
          </div>
          <div class="footer-column">
            <h3>Departments</h3>
            <ul>
              <li><a href="#">Health & Social Services</a></li>
              <li><a href="#">Engineering & Infrastructure</a></li>
              <li><a href="#">Finance</a></li>
              <li><a href="#">Urban Planning</a></li>
              <li><a href="#">Environmental Management</a></li>
            </ul>
          </div>
          <div class="footer-column">
            <h3>Contact Us</h3>
            <div class="contact-info">
              <span>Address:</span>
              <p>Town House, Julius Nyerere Way, Harare</p>
            </div>
            <div class="contact-info">
              <span>Phone:</span>
              <p>+263-242-751823</p>
            </div>
            <div class="contact-info">
              <span>Email:</span>
              <p>info@hararecity.gov.zw</p>
            </div>
            <div class="social-links">
              <a href="#"><span>FB</span></a>
              <a href="#"><span>TW</span></a>
              <a href="#"><span>IG</span></a>
              <a href="#"><span>YT</span></a>
            </div>
          </div>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2025 Harare City Council. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
      <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <h2 class="modal-title">Staff Login</h2>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <form id="loginForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required />
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required />
          </div>
          <button type="submit" class="submit-btn">Login</button>
        </form>
      </div>
    </div>

    <script>
      // Function to open login modal
      function openModal() {
        document.getElementById("loginModal").style.display = "flex";
      }

      // Function to close login modal
      function closeModal() {
        document.getElementById("loginModal").style.display = "none";
      }

      // Close modal when clicking outside the content
      window.onclick = function (event) {
        const modal = document.getElementById("loginModal");
        if (event.target === modal) {
          closeModal();
        }
      };
      
      // Show the modal if there was an error with login
      <?php if (!empty($error_message)): ?>
      document.addEventListener("DOMContentLoaded", function() {
          openModal();
      });
      <?php endif; ?>
    </script>
</body>
</html>