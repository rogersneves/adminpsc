export default function InputError({ message }) {
    if (!message) {
        return null;
    }

    return (
        <p className="text-sm text-destructive" role="alert">
            {message}
        </p>
    );
}
