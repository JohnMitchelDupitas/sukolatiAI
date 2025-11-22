
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Animal</title>
</head>
<body>
    <div>
        <h1>Add New Animal</h1>

        <form action="{{ url('api/products') }}" method="POST">
            @csrf
            <div>
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required maxlength="40">
            </div>

            <div>
                <label for="type">Type:</label>
                <input type="text" id="type" name="type" required maxlength="100">
            </div>

            <div>
                <label for="breed">Breed:</label>
                <input type="text" id="breed" name="breed" required maxlength="100">
            </div>

            <div>
                <label for="health_status">Health Status:</label>
                <input type="text" id="health_status" name="health_status" required maxlength="100">
            </div>

            <div>
                <label for="birth_date">Birth Date:</label>
                <input type="date" id="birth_date" name="birth_date" required>
            </div>

            <button type="submit">Add Animal</button>
        </form>
    </div>
</body>
</html>
