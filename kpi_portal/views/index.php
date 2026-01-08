<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Covenant University KPI Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --cu-purple: #4B0082;
            --cu-green: #228B22;
        }

        body {
            background: #f8f9fa;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background-color: rgba(0, 0, 0, 0.2);
        }

        .hero {
            /* Replace with your chosen image URL */
            background-image: url('https://yourdomain.com/images/covenant-university-campus.jpg');
            background-size: cover;
            background-position: center;
            padding: 6rem 1rem;
            text-align: center;
            position: relative;
            color: #fff;
        }

        .hero::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.45); /* dark overlay for readability */
        }

        .hero h1, .hero p {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 2.8rem;
            font-weight: bold;
        }

        .hero p {
            font-size: 1.2rem;
            margin-top: 1rem;
            color: #e0e0e0;
        }

        .card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
            color: #333;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .card-icon {
            font-size: 3rem;
            color: var(--cu-green);
            margin-top: 1.5rem;
        }

        .btn-cu {
            background-color: var(--cu-purple);
            color: white;
            border-radius: 20px;
        }

        .btn-cu:hover {
            background-color: #360061;
            color: white;
        }

        footer {
            background: #111;
            color: #ccc;
            text-align: center;
            padding: 1rem;
            margin-top: 3rem;
        }

        .modal-content {
            color: #333;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold text-white" href="#">Covenant University KPI Portal</a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero">
    <h1>Welcome to Covenant University KPI Portal</h1>
    <p>
        <strong>Executive Address:</strong><br>
        "In pursuit of academic excellence and operational efficiency, the Covenant University KPI Portal
        empowers every staff and department to track, assess, and improve performance
        in alignment with the university’s mission of raising a new generation of leaders."
    </p>
</section>

<!-- Features Section -->
<div class="container mb-5">
    <div class="row g-4 justify-content-center">
        <!-- Staff Dashboard -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="card-icon">👨‍💼</div>
                    <h5 class="card-title mt-3 fw-bold">Dashboard</h5>
                    <p class="text-muted">Access your personalized performance and KPI reports.</p>
                    <a href="login.php" class="btn btn-cu mt-2">Go to Dashboard</a>
                </div>
            </div>
        </div>

        <!-- Help Desk -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="card-icon">💬</div>
                    <h5 class="card-title mt-3 fw-bold">Help Desk</h5>
                    <p class="text-muted">Get support or report issues to the system administrators.</p>
                    <a href="help_desk.php" class="btn btn-cu mt-2">Visit Help Desk</a>
                </div>
            </div>
        </div>

        <!-- About KPI Portal -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="card-icon">📊</div>
                    <h5 class="card-title mt-3 fw-bold">About KPI Portal</h5>
                    <p class="text-muted">Learn more about the university’s performance management system.</p>
                    <button class="btn btn-cu mt-2" data-bs-toggle="modal" data-bs-target="#aboutModal">Learn More</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- About Modal -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="aboutModalLabel">About Covenant University KPI Portal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>
            The Covenant University KPI Portal serves as a performance evaluation platform that
            helps the University track institutional, departmental, and individual targets.
            Staff can view assigned metrics, submit reports, and monitor progress in real time.
        </p>
        <p>
            Administrators can manage performance records, approve entries, and generate
            summaries that promote accountability, excellence, and continuous improvement.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-cu" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<footer>
    <p>© <?= date("Y"); ?> Covenant University | KPI Portal Initiative</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
