from tkinter import *
from tkinter import filedialog, messagebox
from PIL import Image, ImageTk
import os
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.kdf.pbkdf2 import PBKDF2HMAC
from cryptography.hazmat.backends import default_backend
from cryptography.fernet import Fernet
import base64
from stegano import lsb 

root = Tk()
root.title("Steganography - Hide a secret Text Message in an Image")
root.geometry("700x700+180+200")
root.resizable(True,True)
root.configure(bg="#2f4155")

filename = filedialog.askopenfilename(initialdir=os.getcwd(), title="Select Image File",
                                      filetype=(("PNG file", "*.png"), ("JPG File", "*.jpg"), ("All file", "*.*")))

secret = None

def showimage():
    img = Image.open(filename)
    img = ImageTk.PhotoImage(img)
    lbl.configure(image=img, width=420, height=350)
    lbl.image = img

# Add this line at the beginning to specify encoding
# -*- coding: utf-8 -*-

# ...

def Hide():
    global secret

    password = entry_password.get()
    text = text1.get(1.0, END).strip()

    if not text:
        messagebox.showerror("Error", "Please enter the text to hide.")
        return
    
    if not password:
        messagebox.showerror("Error", "Please enter a password.")
        return
        
    # Generate a key from the password
    salt = b'salt_'
    kdf = PBKDF2HMAC(
        algorithm=hashes.SHA256(),
        iterations=100000,
        salt=salt,
        length=32,
        backend=default_backend()
    )
    key = base64.urlsafe_b64encode(kdf.derive(password.encode()))

    # Encrypt the text
    cipher_suite = Fernet(key)
    encrypted_text = cipher_suite.encrypt(text.encode('utf-8')) # Specify encoding

    # Hide the encrypted message in the image
    secret = lsb.hide(str(filename), encrypted_text.decode('utf-8')) # Specify encoding

    messagebox.showinfo("Success", "Text successfully hidden and image saved as 'hidden.png'.")

def Show():
    global secret

    password = entry_password.get()

    if not password:
        messagebox.showerror("Error", "Please enter a password.")
        return

    # Reveal the encrypted message from the image
    encrypted_text = lsb.reveal(filename)

    if not encrypted_text:
        messagebox.showerror("Error", "No hidden data found in the image.")
        return

    # Generate the key from the password
    salt = b'salt_'
    kdf = PBKDF2HMAC(
        algorithm=hashes.SHA256(),
        iterations=100000,
        salt=salt,
        length=32,
        backend=default_backend()
    )
    key = base64.urlsafe_b64encode(kdf.derive(password.encode()))

    try:
        # Decrypt the message
        cipher_suite = Fernet(key)
        decrypted_text = cipher_suite.decrypt(encrypted_text.encode())

        text1.delete(1.0, END)
        text1.insert(END, decrypted_text.decode())
    except Exception:
        messagebox.showerror("Error", "Incorrect password or invalid data in the image.")

def save():
    global secret

    if secret is None:
        messagebox.showerror("Error", "No hidden data to save.")
        return

    secret.save("hidden.png")

image_icon = PhotoImage(file="C:\\Users\\SHANM\\OneDrive\\Pictures\\Screenshots\\ho (2).png")
root.iconphoto(False, image_icon)

logo = PhotoImage(file="C:\\Users\\SHANM\\OneDrive\\Pictures\\Screenshots\\ho (2).png")
Label(root, text="STEGANOGRAPHY", bg="#2d4155", fg="white", font="arial 25 bold").place(x=100, y=20)

f = Frame(root, bd=3, bg="lightgreen", width=700, height=400, relief=GROOVE)
f.place(x=10, y=100)


lbl = Label(f, bg="lightgreen")
lbl.place(x=15, y=10)

frame2 = Frame(root, bd=3, bg="white", width=500, height=400, relief=GROOVE)
frame2.place(x=500, y=100)


text1 = Text(frame2, font="Roboto 20", bg="white", fg="black", relief=GROOVE, wrap=WORD)
text1.place(x=0, y=0, width=450, height=400)

scrollbar1 = Scrollbar(frame2)
scrollbar1.place(x=480, y=0, height=380)
scrollbar1.configure(command=text1.yview)
text1.configure(yscrollcommand=scrollbar1.set)

frame3 = Frame(root, bd=3, bg="#2f4155", width=500, height=150, relief=GROOVE)
frame3.place(x=10, y=500)

Button(frame3, text="Open Image", width=13, height=3, font="arial 14 bold", command=showimage).place(x=20, y=40)
Button(frame3, text="Save Image", width=13, height=3, font="arial 14 bold", command=save).place(x=200, y=40)
Label(frame3, text="Picture, Image, Photo File", bg="#2f4155", fg="yellow").place(x=20, y=5)

# ... (previous code)

frame4 = Frame(root, bd=3, bg="#2f4155", width=500, height=150, relief=GROOVE)
frame4.place(x=503, y=500)


Button(frame4, text="Hide Data 😊", width=10, height=2, font="arial 14 bold", command=Hide).place(x=20, y=30)
Button(frame4, text="Show Data", width=10, height=2, font="arial 14 bold", command=Show).place(x=180, y=30)
Label(frame4, text="Picture, Image, Photo File", bg="#2f4155", fg="yellow").place(x=20, y=5)

entry_password = Entry(frame4, font="Roboto 14", bg="white", fg="black", relief=GROOVE, show="*")
entry_password.place(x=20, y=105, width=290, height=30)
Label(frame4, text="Password 🔑", bg="#2f4155", fg="yellow").place(x=20, y=85)

root.mainloop()
