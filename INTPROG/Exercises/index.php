<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$team_members = [
    [ "name" => "Andrino, Carlz D.", "age" => 20, "location" => "Molino 3, Bacoor, Cavite", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Online Games, Reading, Cooking", "img" => "images/andrino.jpg" ],
    [ "name" => "Bernal, Loid Russel A.", "age" => 20, "location" => "Poblacion, Muntinlupa City", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Gaming, Watching Movies, Doom Scrolling", "img" => "images/bernal.jpg" ],
    [ "name" => "Garcia, Kyla C.", "age" => 20, "location" => "Bayanan, Muntinlupa City", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Singing, Watching Movies, Listening to Music", "img" => "images/garcia.jpg" ],
    [ "name" => "Grejarte, Althea Kyle N.", "age" => 20, "location" => "Putatan, Muntinlupa City", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Reading, Gaming, Sketching", "img" => "images/grejarte.jpg" ],
    [ "name" => "Hernandez, John Marcel V.", "age" => 20, "location" => "GMA, Cavite, Brgy. F. Reyes", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Playing Guitar, Reading, Jogging & Walking, Singing", "img" => "images/hernandez.jpg" ],
    [ "name" => "Manzano, Ivan Roy L.", "age" => 19, "location" => "Prinza, Poblacion, Muntinlupa City", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Watching Movies, Jogging", "img" => "images/manzano.jpg" ],
    [ "name" => "Perez, Pauline Angel T.", "age" => 20, "location" => "NBP Poblacion, Muntinlupa City", "course" => "BSIT", "year" => "3rd Year", "hobbies" => "Cafe Hopping", "img" => "images/perez.jpg" ]
];

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

$filtered_members = $team_members;
if ($searchTerm !== '') {
    $needle = strtolower($searchTerm);
    $filtered_members = array_filter($team_members, function ($member) use ($needle) {
        $haystack = strtolower($member['name'] . ' ' . $member['location'] . ' ' . $member['hobbies']);
        return strpos($haystack, $needle) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Our Team | Exer 3 - GET</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
    }

    body {
        background-color: #14181f;
        color: #eaeaea;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    header {
        background: linear-gradient(135deg, #1f2733, #171c25);
        border-bottom: 3px solid #4d7cfe;
        text-align: center;
        padding: 30px 20px;
    }

    header h1 {
        font-size: 32px;
        letter-spacing: 1px;
    }

    header p {
        margin-top: 6px;
        color: #9aa4b2;
        font-size: 15px;
    }

    .search-bar {
        text-align: center;
        margin: 24px 0 0;
    }

    .search-bar form {
        display: inline-flex;
        gap: 8px;
        width: 85%;
        max-width: 420px;
    }

    .search-bar input[type="text"] {
        flex: 1;
        padding: 11px 18px;
        border-radius: 999px;
        border: 1px solid #333c4a;
        background: #1c222c;
        color: #eaeaea;
        font-size: 14px;
        outline: none;
    }

    .search-bar input[type="text"]:focus {
        border-color: #4d7cfe;
    }

    .search-bar button {
        padding: 11px 20px;
        border-radius: 999px;
        border: none;
        background: #4d7cfe;
        color: #fff;
        font-size: 14px;
        cursor: pointer;
    }

    .search-bar button:hover {
        background: #3d68e0;
    }

    .search-info {
        text-align: center;
        color: #9aa4b2;
        font-size: 13px;
        margin-top: 10px;
    }

    .search-info a {
        color: #4d7cfe;
        text-decoration: none;
    }

    main {
        flex: 1;
        padding: 30px 5vw 60px;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 22px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .card {
        background: #1c222c;
        border: 1px solid #2a3140;
        border-radius: 12px;
        overflow: hidden;
    }

    .card img {
        width: 100%;
        height: 190px;
        object-fit: cover;
        display: block;
    }

    .card .info {
        padding: 16px;
    }

    .card .info h2 {
        font-size: 17px;
        margin-bottom: 4px;
    }

    .card .info .course {
        color: #4d7cfe;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .card .info p {
        font-size: 13px;
        color: #9aa4b2;
        margin: 3px 0;
    }

    .no-results {
        text-align: center;
        color: #9aa4b2;
        font-size: 14px;
        padding: 40px 0;
    }

    footer {
        background: #171c25;
        text-align: center;
        padding: 18px;
        color: #9aa4b2;
        font-size: 13px;
    }
</style>
</head>
<body>

<header>
    <h1>OUR TEAM</h1>
    <p>BSIT 3rd Year | Integrative Programming &amp; Technologies</p>
</header>

<div class="search-bar">

    <form method="GET" action="">
        <input
            type="text"
            name="search"
            id="searchInput"
            value="<?php echo htmlspecialchars($searchTerm); ?>"
            placeholder="Search by name, location, or hobby...">
        <button type="submit">Search</button>
    </form>
    <?php if ($searchTerm !== ''): ?>
        <p class="search-info">
            Showing results for "<?php echo htmlspecialchars($searchTerm); ?>"
            &middot; <a href="exer3.php">Clear search</a>
        </p>
    <?php endif; ?>
</div>

<main>
    <div class="grid" id="teamGrid">
        <?php if (count($filtered_members) > 0): ?>
            <?php foreach ($filtered_members as $member): ?>
                <div class="card">
                    <img src="<?php echo htmlspecialchars($member['img']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>">
                    <div class="info">
                        <h2><?php echo htmlspecialchars($member['name']); ?></h2>
                        <p class="course"><?php echo htmlspecialchars($member['course']); ?> &middot; <?php echo htmlspecialchars($member['year']); ?></p>
                        <p><b>Age:</b> <?php echo (int)$member['age']; ?></p>
                        <p><b>Location:</b> <?php echo htmlspecialchars($member['location']); ?></p>
                        <p><b>Hobbies:</b> <?php echo htmlspecialchars($member['hobbies']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (count($filtered_members) === 0): ?>
        <p class="no-results">No matching member found.</p>
    <?php endif; ?>
</main>

</body>
</html>
